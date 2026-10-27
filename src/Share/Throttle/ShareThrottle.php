<?php

declare(strict_types=1);

namespace Contenir\Preview\Share\Throttle;

use Psr\Clock\ClockInterface;

use function hash;
use function implode;

/**
 * Slows down guessing a link's code (§9.3): after PER_VISITOR wrong codes from one address in
 * WINDOW seconds, that address is turned away from the link until the window closes; after
 * PER_LINK from any addresses, everyone is, so guessing from many addresses gains little. Each
 * link counts apart, so one guesser locks out no other link.
 *
 * @api
 */
final readonly class ShareThrottle
{
    /** Wrong codes one address may enter for a link in a window. */
    public const int PER_VISITOR = 5;

    /** Wrong codes every address together may enter for a link in a window. */
    public const int PER_LINK = 25;

    /** How long a window lasts, in seconds, from its first wrong code. */
    public const int WINDOW = 900;

    public function __construct(
        private CounterInterface $counter,
        private ClockInterface $clock,
    ) {}

    /**
     * A key a cache and a file system both take, which gives away neither the link nor the address.
     */
    private static function key(string ...$parts): string
    {
        return hash('sha1', implode("\n", $parts));
    }

    /**
     * Counts a wrong code from $visitor for the link.
     */
    public function fail(string $shareId, string $visitor): void
    {
        $this->count(self::key($shareId, $visitor));
        $this->count(self::key($shareId));
    }

    /**
     * Whether $visitor is turned away from the link for now.
     */
    public function isBlocked(string $shareId, string $visitor): bool
    {
        return (
            $this->reached(self::key($shareId, $visitor), self::PER_VISITOR)
                || $this->reached(self::key($shareId), self::PER_LINK)
        );
    }

    private function count(string $key): void
    {
        $now   = $this->clock->now()->getTimestamp();
        $entry = $this->counter->get($key);
        if (null === $entry || $entry[1] <= $now) {
            $this->counter->put($key, 1, $now + self::WINDOW);

            return;
        }

        $this->counter->put($key, $entry[0] + 1, $entry[1]);
    }

    /**
     * Whether a count still in its window has reached $limit.
     */
    private function reached(string $key, int $limit): bool
    {
        $entry = $this->counter->get($key);

        return null !== $entry && $entry[1] > $this->clock->now()->getTimestamp() && $entry[0] >= $limit;
    }
}
