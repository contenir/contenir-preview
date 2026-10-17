<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Clock;

use Contenir\Preview\Clock\SystemClock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function time;

/**
 * The system's own time, for a CMS or site with no PSR-20 clock of its own.
 */
#[Group('unit')]
final class SystemClockTest extends TestCase
{
    #[Test]
    public function tellsTheSystemsTime(): void
    {
        $before = time();
        $now    = (new SystemClock())->now()->getTimestamp();

        $this->assertSame([true, true], [$now >= $before, $now <= time()]);
    }
}
