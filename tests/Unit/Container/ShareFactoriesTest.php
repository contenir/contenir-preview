<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Container;

use Contenir\Preview\Container\ClockFactory;
use Contenir\Preview\Container\InvalidConfiguration;
use Contenir\Preview\Container\PreviewOptions;
use Contenir\Preview\Container\ShareEntryFactory;
use Contenir\Preview\Container\ShareStoreFactory;
use Contenir\Preview\Container\ShareThrottleFactory;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Share\PhpDbShareStore;
use Contenir\Preview\Share\ShareEntry;
use Contenir\Preview\Share\ShareStoreInterface;
use Contenir\Preview\Share\Throttle\ShareThrottle;
use Contenir\Preview\Tests\TestAsset\ArrayCache;
use Contenir\Preview\Tests\TestAsset\ArrayContainer;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Tests\TestAsset\Previews;
use Contenir\Preview\Tests\TestAsset\Share\ArrayCounter;
use Contenir\Preview\Tests\TestAsset\Share\InMemoryShareStore;
use Contenir\Preview\Tests\Trait\AdapterPlatformStubTrait;
use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function implode;

/**
 * The shared previews' services, built from the `contenir_preview.share` configuration. The
 * file counter is tested with real files, as integration.
 */
#[Group('unit')]
final class ShareFactoriesTest extends TestCase
{
    use AdapterPlatformStubTrait;

    #[Test]
    public function buildsTheEntryFromItsServices(): void
    {
        $entry = (new ShareEntryFactory())(new ArrayContainer([
            PreviewOptions::class      => PreviewOptions::fromAppConfig(Previews::appConfig(['share' => []])),
            ShareStoreInterface::class => new InMemoryShareStore(),
            ResolverRegistry::class    => new ResolverRegistry([]),
            PreviewContext::class      => new PreviewContext(),
            ShareThrottle::class       => new ShareThrottle(new ArrayCounter(), new FrozenClock()),
            ClockFactory::SERVICE      => new FrozenClock(),
        ]));

        $this->assertInstanceOf(ShareEntry::class, $entry);
    }

    #[Test]
    public function countsWrongCodesInTheConfiguredCache(): void
    {
        $cache    = new ArrayCache();
        $throttle = (new ShareThrottleFactory())(new ArrayContainer([
            PreviewOptions::class => PreviewOptions::fromAppConfig(Previews::appConfig([
                'replay' => ['cache' => 'PreviewCache'],
            ])),
            ClockFactory::SERVICE => new FrozenClock(),
            'PreviewCache'        => $cache,
        ]));
        $throttle->fail('link', 'visitor');

        $this->assertMatchesRegularExpression(
            '/^contenir_share_[0-9a-f]{40}\|contenir_share_[0-9a-f]{40}$/',
            implode('|', array_keys($cache->values)),
        );
    }

    #[Test]
    public function keepsLinksInTheNamedDatabase(): void
    {
        $store = (new ShareStoreFactory())(new ArrayContainer([
            PreviewOptions::class => PreviewOptions::fromAppConfig(Previews::appConfig(['share' => [
                'adapter' => 'SiteDb',
            ]])),
            'SiteDb'              => $this->adapterNamed('SQLite'),
        ]));

        $this->assertInstanceOf(PhpDbShareStore::class, $store);
    }

    #[Test]
    public function keepsLinksInTheSitesDatabaseByDefault(): void
    {
        $store = (new ShareStoreFactory())(new ArrayContainer([
            PreviewOptions::class   => PreviewOptions::fromAppConfig(Previews::appConfig(['share' => []])),
            AdapterInterface::class => $this->adapterNamed('MySQL'),
        ]));

        $this->assertInstanceOf(PhpDbShareStore::class, $store);
    }

    #[Test]
    public function refusesToKeepLinksWhileSharingIsOff(): void
    {
        $this->expectExceptionObject(new InvalidConfiguration(
            'Shared previews are off: the configuration has no contenir_preview.share.',
        ));

        (new ShareStoreFactory())(new ArrayContainer([
            PreviewOptions::class => PreviewOptions::fromAppConfig(Previews::appConfig()),
        ]));
    }
}
