<?php

declare(strict_types=1);

namespace Contenir\Preview;

use Contenir\Preview\Container\ClockFactory;
use Contenir\Preview\Container\PreviewContextFactory;
use Contenir\Preview\Container\PreviewEntryFactory;
use Contenir\Preview\Container\PreviewOptions;
use Contenir\Preview\Container\PreviewOptionsFactory;
use Contenir\Preview\Container\PreviewResponseFactory;
use Contenir\Preview\Container\ReplayGuardFactory;
use Contenir\Preview\Container\ResolverRegistryFactory;
use Contenir\Preview\Container\TokenVerifierFactory;
use Contenir\Preview\Replay\ReplayGuardInterface;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Token\TokenVerifier;

/**
 * The services a renderer builds from the application's `contenir_preview` configuration, for
 * any PSR-11 container: each framework adapter registers them and adds its own.
 *
 * @api
 */
final class ConfigProvider
{
    /**
     * @return array{factories: array<string, class-string>}
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                PreviewOptions::class       => PreviewOptionsFactory::class,
                ClockFactory::SERVICE       => ClockFactory::class,
                ReplayGuardInterface::class => ReplayGuardFactory::class,
                ResolverRegistry::class     => ResolverRegistryFactory::class,
                TokenVerifier::class        => TokenVerifierFactory::class,
                PreviewEntry::class         => PreviewEntryFactory::class,
                PreviewResponse::class      => PreviewResponseFactory::class,
                PreviewContext::class       => PreviewContextFactory::class,
            ],
        ];
    }

    /**
     * @return array{dependencies: array{factories: array<string, class-string>}}
     */
    public function __invoke(): array
    {
        return ['dependencies' => $this->getDependencies()];
    }
}
