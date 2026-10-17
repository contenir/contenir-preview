<?php

declare(strict_types=1);

namespace Contenir\Preview\Replay;

use Contenir\Preview\PreviewConfig;
use Override;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

use function max;

/**
 * A replay guard over a PSR-16 cache, which servers sharing the cache share too. Each id is kept
 * until its token could no longer be accepted. A cache without an atomic add leaves a narrow window
 * in which two servers both accept the same token at the same instant.
 */
final readonly class CacheReplayGuard implements ReplayGuardInterface
{
    public function __construct(
        private CacheInterface $cache,
        private ClockInterface $clock,
        private string $prefix = 'contenir_preview_',
    ) {}

    #[Override]
    public function claim(string $tokenId, int $expiresAt): bool
    {
        $key = $this->prefix . $tokenId;
        $ttl = max(1, $expiresAt + PreviewConfig::LEEWAY - $this->clock->now()->getTimestamp());

        try {
            return ! $this->cache->has($key) && $this->cache->set($key, $expiresAt, $ttl);
        } catch (InvalidArgumentException) {
            // A key the cache refuses cannot be remembered, so the token cannot safely be accepted
            return false;
        }
    }
}
