<?php

declare(strict_types=1);

namespace Contenir\Preview\Share\Throttle;

/**
 * Where ShareThrottle keeps its counts of wrong codes, each until its window closes. A site on
 * more than one server needs one all of them share.
 *
 * @api
 */
interface CounterInterface
{
    /**
     * A count and when its window closes (seconds since the epoch), or null when there is none.
     *
     * @return array{int, int}|null
     */
    public function get(string $key): ?array;

    /**
     * Keeps a count until its window closes.
     *
     * @param int $until Seconds since the epoch
     */
    public function put(string $key, int $count, int $until): void;
}
