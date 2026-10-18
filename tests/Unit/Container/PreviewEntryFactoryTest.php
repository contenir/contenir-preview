<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Container;

use Contenir\Preview\Container\ClockFactory;
use Contenir\Preview\Container\PreviewEntryFactory;
use Contenir\Preview\Container\PreviewOptions;
use Contenir\Preview\Container\PreviewResponseFactory;
use Contenir\Preview\Container\TokenVerifierFactory;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\Replay\InMemoryReplayGuard;
use Contenir\Preview\Replay\ReplayGuardInterface;
use Contenir\Preview\Resolver\PathResolver;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Tests\TestAsset\ArrayContainer;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Tests\TestAsset\Previews;
use Contenir\Preview\Token\TokenVerifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The verifier, the entry and the response, each built over the configured services.
 */
#[Group('unit')]
final class PreviewEntryFactoryTest extends TestCase
{
    #[Test]
    public function answersWithTheConfiguredIssuerAsTheOnlyFramer(): void
    {
        $response = (new PreviewResponseFactory())($this->container(new PreviewContext()));

        $this->assertSame('frame-ancestors ' . Previews::ISSUER, $response->headers()['Content-Security-Policy']);
    }

    #[Test]
    public function opensAPreviewWithTheConfiguredServices(): void
    {
        $context  = new PreviewContext();
        $services = $this->container($context);

        $target = (new PreviewEntryFactory())(new ArrayContainer([
            TokenVerifier::class    => (new TokenVerifierFactory())($services),
            ResolverRegistry::class => new ResolverRegistry(['site.page.home' => new PathResolver()]),
            PreviewContext::class   => $context,
        ]))
            ->open('POST', Previews::token(new FrozenClock()));

        $this->assertSame(['/spa-rituals', '12'], [$target->path, $context->request()?->userId]);
    }

    private function container(PreviewContext $context): ArrayContainer
    {
        $clock = new FrozenClock();

        return new ArrayContainer([
            PreviewOptions::class       => PreviewOptions::fromAppConfig(Previews::appConfig()),
            ClockFactory::SERVICE       => $clock,
            ReplayGuardInterface::class => new InMemoryReplayGuard($clock),
            PreviewContext::class       => $context,
        ]);
    }
}
