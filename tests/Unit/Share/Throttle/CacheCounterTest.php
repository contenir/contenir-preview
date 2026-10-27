<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share\Throttle;

use Contenir\Preview\Share\Throttle\CacheCounter;
use Contenir\Preview\Tests\TestAsset\ArrayCache;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Counts in a PSR-16 cache, each kept until its window closes.
 */
#[Group('unit')]
final class CacheCounterTest extends TestCase
{
    private const int NOW = 1_792_236_000;

    /**
     * @return array<string, array{mixed}>
     */
    public static function valuesNotACount(): array
    {
        return [
            'text'                 => ['3 1792236900'],
            'one number'           => [[3]],
            'three numbers'        => [[3, 1_792_236_900, 1]],
            'a count not a number' => [['3', 1_792_236_900]],
            'an end not a number'  => [[3, '1792236900']],
        ];
    }

    #[Test]
    public function countsNothingWhereTheCacheRefusesTheKey(): void
    {
        $counter = new CacheCounter(new ArrayCache(refuse: 'share_key'), new FrozenClock(), 'share_');
        $counter->put('key', 3, self::NOW + 900);

        $this->assertNull($counter->get('key'));
    }

    #[Test]
    public function keepsACountAtLeastASecond(): void
    {
        $cache = new ArrayCache();
        (new CacheCounter($cache, new FrozenClock(self::NOW), 'share_'))->put('key', 3, self::NOW);

        $this->assertSame(1, $cache->ttls['share_key']);
    }

    #[Test]
    public function keepsACountUntilItsWindowCloses(): void
    {
        $cache   = new ArrayCache();
        $counter = new CacheCounter($cache, new FrozenClock(self::NOW), 'share_');
        $counter->put('key', 3, self::NOW + 900);

        $this->assertSame([[3, self::NOW + 900], 900], [$counter->get('key'), $cache->ttls['share_key']]);
    }

    #[Test]
    #[DataProvider('valuesNotACount')]
    public function readsNoCountFromAnythingElse(mixed $value): void
    {
        $cache = new ArrayCache();
        $cache->set('share_key', $value);

        $this->assertNull((new CacheCounter($cache, new FrozenClock(), 'share_'))->get('key'));
    }
}
