<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share\Throttle;

use Contenir\Preview\Share\Throttle\ShareThrottle;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Tests\TestAsset\Share\ArrayCounter;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_values;
use function implode;

/**
 * Wrong codes counted per address and per link, each in a window from its first.
 */
#[Group('unit')]
final class ShareThrottleTest extends TestCase
{
    private ArrayCounter $counter;

    private FrozenClock $clock;

    private ShareThrottle $throttle;

    #[Test]
    public function blocksAnAddressAfterFiveWrongCodes(): void
    {
        $this->failTimes('link', 'visitor', ShareThrottle::PER_VISITOR - 1);
        $before = $this->throttle->isBlocked('link', 'visitor');
        $this->failTimes('link', 'visitor', 1);

        $this->assertSame([false, true], [$before, $this->throttle->isBlocked('link', 'visitor')]);
    }

    #[Test]
    public function blocksEveryoneFromALinkGuessedFromManyAddresses(): void
    {
        for ($visitor = 1; $visitor < ShareThrottle::PER_LINK; $visitor++) {
            $this->failTimes('link', "visitor {$visitor}", 1);
        }
        $before = $this->throttle->isBlocked('link', 'newcomer');
        $this->failTimes('link', 'one more', 1);

        $this->assertSame([false, true], [$before, $this->throttle->isBlocked('link', 'newcomer')]);
    }

    #[Test]
    public function keepsCountingInTheWindowOfTheFirstWrongCode(): void
    {
        $this->failTimes('link', 'visitor', 1);
        $this->clock->advance(600);
        $this->failTimes('link', 'visitor', ShareThrottle::PER_VISITOR - 1);
        $blocked = $this->throttle->isBlocked('link', 'visitor');
        $this->clock->advance(300);

        $this->assertSame([true, false], [$blocked, $this->throttle->isBlocked('link', 'visitor')]);
    }

    #[Test]
    public function keepsItsKeysFreeOfTheLinkAndTheAddress(): void
    {
        $this->failTimes('link', '203.0.113.7', 1);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{40}\|[0-9a-f]{40}$/',
            implode('|', array_keys($this->counter->counts)),
        );
    }

    #[Test]
    public function locksOutNoOtherLink(): void
    {
        $this->failTimes('link', 'visitor', ShareThrottle::PER_LINK);

        $this->assertFalse($this->throttle->isBlocked('another link', 'visitor'));
    }

    #[Test]
    public function startsANewWindowOnceTheLastHasClosed(): void
    {
        $this->failTimes('link', 'visitor', ShareThrottle::PER_VISITOR);
        $this->clock->advance(ShareThrottle::WINDOW);
        $this->failTimes('link', 'visitor', 1);

        $this->assertSame(
            [false, [1, 1_792_236_000 + (2 * ShareThrottle::WINDOW)]],
            [$this->throttle->isBlocked('link', 'visitor'), array_values($this->counter->counts)[0]],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->counter  = new ArrayCounter();
        $this->clock    = new FrozenClock();
        $this->throttle = new ShareThrottle($this->counter, $this->clock);
    }

    private function failTimes(string $link, string $visitor, int $times): void
    {
        for ($time = 0; $time < $times; $time++) {
            $this->throttle->fail($link, $visitor);
        }
    }
}
