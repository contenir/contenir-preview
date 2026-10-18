<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Container;

use Contenir\Preview\Clock\SystemClock;
use Contenir\Preview\Container\ClockFactory;
use Contenir\Preview\Container\PreviewOptions;
use Contenir\Preview\Tests\TestAsset\ArrayContainer;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Tests\TestAsset\Previews;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ClockFactoryTest extends TestCase
{
    #[Test]
    public function usesTheConfiguredClock(): void
    {
        $clock = new FrozenClock();

        $this->assertSame($clock, (new ClockFactory())(new ArrayContainer([
            PreviewOptions::class => PreviewOptions::fromAppConfig(Previews::appConfig(['clock' => 'AppClock'])),
            'AppClock'            => $clock,
        ])));
    }

    #[Test]
    public function usesTheSystemClockOtherwise(): void
    {
        $this->assertInstanceOf(SystemClock::class, (new ClockFactory())(new ArrayContainer([
            PreviewOptions::class => PreviewOptions::fromAppConfig(Previews::appConfig()),
        ])));
    }
}
