<?php

declare(strict_types=1);

namespace Contenir\Preview\Container;

use Contenir\Preview\PreviewContext;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Share\ShareEntry;
use Contenir\Preview\Share\SharePass;
use Contenir\Preview\Share\ShareStoreInterface;
use Contenir\Preview\Share\Throttle\ShareThrottle;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

final class ShareEntryFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration
     */
    public function __invoke(ContainerInterface $container): ShareEntry
    {
        return new ShareEntry(
            Service::get($container, ShareStoreInterface::class),
            Service::get($container, ResolverRegistry::class),
            Service::get($container, PreviewContext::class),
            new SharePass(Service::get($container, PreviewOptions::class)->config),
            Service::get($container, ShareThrottle::class),
            Service::get($container, ClockInterface::class, ClockFactory::SERVICE),
        );
    }
}
