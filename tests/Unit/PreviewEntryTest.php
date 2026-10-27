<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewEntry;
use Contenir\Preview\Resolver\PathResolver;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Tests\TestAsset\Tokens;
use Contenir\Preview\Token\TokenVerifier;
use Contenir\SiteToken\Replay\InMemoryReplayGuard;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A request to a site's preview endpoint taken as far as the page to render: posted, verified,
 * resolved, and the preview context begun.
 */
#[Group('unit')]
final class PreviewEntryTest extends TestCase
{
    #[Test]
    public function beginsNoPreviewForASectionTheSiteCannotShow(): void
    {
        $context = new PreviewContext();

        try {
            $this->entry($context, [])->open('POST', Tokens::sign(Tokens::header(), Tokens::payload()));
            $this->fail('The preview was opened.');
        } catch (InvalidPreview $refusal) {
            $this->assertSame([404, false], [$refusal->status, $context->isActive()]);
        }
    }

    #[Test]
    public function opensAPostedPreviewAtItsPage(): void
    {
        $context = new PreviewContext();

        $target = $this->entry($context, ['site.page.home' => new PathResolver()])->open('post', Tokens::sign(
            Tokens::header(),
            Tokens::payload(),
        ));

        $this->assertSame(['/spa-rituals', Tokens::TOKEN_ID], [$target->path, $context->request()?->tokenId]);
    }

    #[Test]
    public function refusesAPreviewFetchedRatherThanPosted(): void
    {
        $context = new PreviewContext();

        try {
            $this->entry($context, ['site.page.home' => new PathResolver()])->open('GET', Tokens::sign(
                Tokens::header(),
                Tokens::payload(),
            ));
            $this->fail('The preview was opened.');
        } catch (InvalidPreview $refusal) {
            $this->assertSame([405, false], [$refusal->status, $context->isActive()]);
        }
    }

    /**
     * @param array<string, \Contenir\Preview\Resolver\PreviewResolverInterface> $resolvers
     */
    private function entry(PreviewContext $context, array $resolvers): PreviewEntry
    {
        $clock = new FrozenClock();

        return new PreviewEntry(
            new TokenVerifier(Tokens::config(), $clock, new InMemoryReplayGuard($clock)),
            new ResolverRegistry($resolvers),
            $context,
        );
    }
}
