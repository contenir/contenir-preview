<?php

declare(strict_types=1);

namespace Contenir\Preview\Token;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewConfig;
use Psr\Clock\ClockInterface;

use function is_int;

/**
 * A token's lifetime (§3.2): at most PreviewConfig::MAX_LIFETIME seconds, begun and not yet
 * ended, give or take the clocks' leeway.
 *
 * @internal
 */
final readonly class Lifetime
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    /**
     * @param array<array-key, mixed> $payload
     * @return array{int, int} The token's issue and expiry times
     * @throws InvalidPreview
     */
    public function of(array $payload): array
    {
        /** @var mixed $issuedAt */
        $issuedAt = $payload['iat'] ?? null;
        /** @var mixed $expiresAt */
        $expiresAt = $payload['exp'] ?? null;
        if (
            ! is_int($issuedAt)
            || ! is_int($expiresAt)
            || $expiresAt < $issuedAt
            || ($expiresAt - $issuedAt) > PreviewConfig::MAX_LIFETIME
        ) {
            throw InvalidPreview::unauthenticated('The preview\'s lifetime is not one this site accepts.');
        }

        $now = $this->clock->now()->getTimestamp();
        if ($issuedAt > ($now + PreviewConfig::LEEWAY)) {
            throw InvalidPreview::unauthenticated(
                'The preview was issued in the future. Check the clocks of the CMS and the site.',
            );
        }
        if (($expiresAt + PreviewConfig::LEEWAY) < $now) {
            throw InvalidPreview::unauthenticated('The preview has expired. Refresh the preview to see it again.');
        }

        return [$issuedAt, $expiresAt];
    }
}
