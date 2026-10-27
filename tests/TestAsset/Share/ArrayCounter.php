<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\TestAsset\Share;

use Contenir\Preview\Share\Throttle\CounterInterface;
use Override;

/**
 * Counts in an array, for testing the throttle without a cache or files.
 */
final class ArrayCounter implements CounterInterface
{
    /** @var array<string, array{int, int}> */
    public array $counts = [];

    #[Override]
    public function get(string $key): ?array
    {
        return $this->counts[$key] ?? null;
    }

    #[Override]
    public function put(string $key, int $count, int $until): void
    {
        $this->counts[$key] = [$count, $until];
    }
}
