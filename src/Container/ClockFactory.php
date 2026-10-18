<?php

declare(strict_types=1);

namespace Contenir\Preview\Container;

use Contenir\Preview\Clock\SystemClock;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * The clock previews are timed by: the configured PSR-20 service, or the system's.
 */
final class ClockFactory
{
    public const string SERVICE = 'contenir_preview.clock';

    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration
     */
    public function __invoke(ContainerInterface $container): ClockInterface
    {
        $name = Service::get($container, PreviewOptions::class)->clock;

        return null === $name ? new SystemClock() : Service::get($container, ClockInterface::class, $name);
    }
}
