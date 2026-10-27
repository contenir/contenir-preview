<?php

declare(strict_types=1);

namespace Contenir\Preview\Container;

use Contenir\Preview\Token\TokenVerifier;
use Contenir\SiteToken\Replay\ReplayGuardInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

final class TokenVerifierFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration
     */
    public function __invoke(ContainerInterface $container): TokenVerifier
    {
        return new TokenVerifier(
            Service::get($container, PreviewOptions::class)->config,
            Service::get($container, ClockInterface::class, ClockFactory::SERVICE),
            Service::get($container, ReplayGuardInterface::class),
        );
    }
}
