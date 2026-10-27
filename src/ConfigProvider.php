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
use Contenir\Preview\Container\ShareEntryFactory;
use Contenir\Preview\Container\ShareStoreFactory;
use Contenir\Preview\Container\ShareThrottleFactory;
use Contenir\Preview\Container\TokenVerifierFactory;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Share\ShareEntry;
use Contenir\Preview\Share\ShareStoreInterface;
use Contenir\Preview\Share\Throttle\ShareThrottle;
use Contenir\Preview\Token\TokenVerifier;
use Contenir\SiteToken\Replay\ReplayGuardInterface;

use function dirname;

/**
 * The services a renderer builds from the application's `contenir_preview` configuration, for
 * any PSR-11 container: each framework adapter registers them and adds its own.
 *
 * The table shared previews are kept in is created by the migrations in `schema/`, listed under
 * `contenir.schema_migrations` for the host's migration runner (the CMS runs them on the site's
 * database).
 *
 * @api
 */
final class ConfigProvider
{
    public const string SCHEMA_NAMESPACE = __NAMESPACE__ . '\\Schema';

    /**
     * The directory of the migrations, for a host that runs them with peptolab/phpdb-migration
     * under {@see SCHEMA_NAMESPACE}.
     */
    public static function schemaPath(): string
    {
        return dirname(__DIR__) . '/schema';
    }

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
                ShareStoreInterface::class  => ShareStoreFactory::class,
                ShareThrottle::class        => ShareThrottleFactory::class,
                ShareEntry::class           => ShareEntryFactory::class,
            ],
        ];
    }

    /**
     * @return array{
     *     dependencies: array{factories: array<string, class-string>},
     *     contenir: array{schema_migrations: array<string, string>},
     * }
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'contenir'     => ['schema_migrations' => [self::SCHEMA_NAMESPACE => self::schemaPath()]],
        ];
    }
}
