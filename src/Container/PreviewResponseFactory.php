<?php

declare(strict_types=1);

namespace Contenir\Preview\Container;

use Contenir\Preview\PreviewResponse;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

final class PreviewResponseFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration
     */
    public function __invoke(ContainerInterface $container): PreviewResponse
    {
        return new PreviewResponse(Service::get($container, PreviewOptions::class)->config);
    }
}
