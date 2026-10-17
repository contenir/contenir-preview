<?php

declare(strict_types=1);

namespace Contenir\Preview\Token;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewConfig;

use function is_string;
use function preg_match;

/**
 * The payload checks a signed token must pass besides its lifetime (§5, steps 5 and 6): the
 * protocol version, the issuer and audience this site expects, and a well-formed one-time id.
 *
 * @internal
 */
final readonly class ClaimRules
{
    public function __construct(
        private PreviewConfig $config,
    ) {}

    /**
     * @param array<array-key, mixed> $payload
     * @throws InvalidPreview
     */
    public function check(array $payload): void
    {
        if (TokenSigner::VERSION !== ($payload['ver'] ?? null)) {
            throw InvalidPreview::unauthenticated('The preview uses a protocol version this site does not know.');
        }
        if (
            $this->config->issuer !== ($payload['iss'] ?? null)
            || $this->config->audience !== ($payload['aud'] ?? null)
        ) {
            throw InvalidPreview::unauthenticated('The preview was not issued for this site.');
        }
    }

    /**
     * @param array<array-key, mixed> $payload
     * @throws InvalidPreview
     */
    public function tokenId(array $payload): string
    {
        /** @var mixed $tokenId */
        $tokenId = $payload['jti'] ?? null;
        if (! is_string($tokenId) || 1 !== preg_match('/^[0-9a-f]{32}$/', $tokenId)) {
            throw InvalidPreview::malformed('The preview has no id.');
        }

        return $tokenId;
    }
}
