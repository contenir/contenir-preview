<?php

declare(strict_types=1);

namespace Contenir\Preview\Token;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewConfig;
use Contenir\Preview\PreviewRequest;
use Contenir\Preview\Replay\ReplayGuardInterface;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

use function hash_equals;
use function hash_hmac;
use function is_string;

/**
 * A site's side: checks a posted preview token the way §5 lays out, in that order, and turns it
 * into a PreviewRequest, or refuses it with the status the protocol gives each failure.
 */
final readonly class TokenVerifier
{
    private ClaimRules $claims;

    private Lifetime $lifetime;

    public function __construct(
        private PreviewConfig $config,
        ClockInterface $clock,
        private ReplayGuardInterface $replay,
    ) {
        $this->claims   = new ClaimRules($config);
        $this->lifetime = new Lifetime($clock);
    }

    /**
     * @throws InvalidPreview
     */
    public function verify(#[SensitiveParameter] ?string $token): PreviewRequest
    {
        $parsed   = ParsedToken::parse($token);
        $expected = hash_hmac('sha256', $parsed->signed, $this->secretFor($parsed->header), binary: true);
        if (null === $parsed->signature || ! hash_equals($expected, $parsed->signature)) {
            throw InvalidPreview::unauthenticated('The preview\'s signature does not match.');
        }

        $this->claims->check($parsed->payload);
        [$issuedAt, $expiresAt] = $this->lifetime->of($parsed->payload);
        $tokenId = $this->claims->tokenId($parsed->payload);
        if (! $this->replay->claim($tokenId, $expiresAt)) {
            throw InvalidPreview::replayed();
        }

        /** @var mixed $userId */
        $userId = $parsed->payload['sub'] ?? null;

        return new PreviewRequest(
            RecordClaim::parse($parsed->payload['rec'] ?? null),
            is_string($userId) ? $userId : '',
            $tokenId,
            (new DateTimeImmutable())->setTimestamp($issuedAt),
            (new DateTimeImmutable())->setTimestamp($expiresAt),
        );
    }

    /**
     * @param array<array-key, mixed> $header
     * @throws InvalidPreview
     */
    private function secretFor(array $header): string
    {
        if ('HS256' !== ($header['alg'] ?? null) || TokenSigner::TYPE !== ($header['typ'] ?? null)) {
            throw InvalidPreview::unauthenticated('The preview is not signed the way this site expects.');
        }

        /** @var mixed $keyId */
        $keyId  = $header['kid'] ?? null;
        $secret = is_string($keyId) ? $this->config->secret($keyId) : null;

        return $secret ?? throw InvalidPreview::unauthenticated(
            'The preview is signed with a key this site does not hold.',
        );
    }
}
