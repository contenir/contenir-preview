<?php

declare(strict_types=1);

namespace Contenir\Preview\Share\Throttle;

use Override;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

use function count;
use function is_array;
use function is_int;
use function max;

/**
 * Counts in a PSR-16 cache, which the site's servers share. A cache that refuses a key counts
 * nothing, which only lets a guesser try again sooner; the codes themselves are no weaker.
 *
 * @api
 */
final readonly class CacheCounter implements CounterInterface
{
    public function __construct(
        private CacheInterface $cache,
        private ClockInterface $clock,
        private string $prefix,
    ) {}

    #[Override]
    public function get(string $key): ?array
    {
        try {
            /** @var mixed $value */
            $value = $this->cache->get($this->prefix . $key);
        } catch (InvalidArgumentException) {
            return null;
        }

        if (! is_array($value) || 2 !== count($value) || ! is_int($value[0] ?? null) || ! is_int($value[1] ?? null)) {
            return null;
        }

        return [$value[0], $value[1]];
    }

    #[Override]
    public function put(string $key, int $count, int $until): void
    {
        try {
            $this->cache->set(
                $this->prefix . $key,
                [$count, $until],
                max(1, $until - $this->clock->now()->getTimestamp()),
            );
        } catch (InvalidArgumentException) {
            return;
        }
    }
}
