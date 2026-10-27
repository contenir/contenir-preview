<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit;

use Contenir\Preview\ConfigProvider;
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
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewEntry;
use Contenir\Preview\PreviewResponse;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Schema\Version20261027000100CreatePreviewShare;
use Contenir\Preview\Share\ShareEntry;
use Contenir\Preview\Share\ShareStoreInterface;
use Contenir\Preview\Share\Throttle\ShareThrottle;
use Contenir\Preview\Token\TokenVerifier;
use Contenir\SiteToken\Replay\ReplayGuardInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function dirname;

/**
 * The services a renderer registers, for any PSR-11 container.
 */
#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function buildsAFreshPreviewContext(): void
    {
        $factory = new PreviewContextFactory();

        static::assertNotSame($factory(), $factory());
    }

    #[Test]
    public function namesTheMigrationsTheHostRuns(): void
    {
        $this->assertSame(
            [dirname(__DIR__, levels: 2) . '/schema', ConfigProvider::SCHEMA_NAMESPACE],
            [
                ConfigProvider::schemaPath(),
                (new ReflectionClass(Version20261027000100CreatePreviewShare::class))->getNamespaceName(),
            ],
        );
    }

    #[Test]
    public function offersItsDependenciesToAdaptersThatMergeThem(): void
    {
        $provider = new ConfigProvider();

        static::assertSame($provider()['dependencies'], $provider->getDependencies());
    }

    #[Test]
    public function registersEveryServiceARendererBuilds(): void
    {
        $provider = new ConfigProvider();

        static::assertSame(
            [
                'dependencies' => [
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
                ],
                'contenir'     => [
                    'schema_migrations' => [
                        ConfigProvider::SCHEMA_NAMESPACE => dirname(__DIR__, levels: 2) . '/schema',
                    ],
                ],
            ],
            $provider(),
        );
    }
}
