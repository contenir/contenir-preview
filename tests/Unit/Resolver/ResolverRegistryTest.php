<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Resolver;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewRecord;
use Contenir\Preview\Resolver\PathResolver;
use Contenir\Preview\Resolver\PreviewResolverInterface;
use Contenir\Preview\Resolver\ResolverRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The resolvers a site registers, one per section it can preview.
 */
#[Group('unit')]
final class ResolverRegistryTest extends TestCase
{
    #[Test]
    public function asksTheSectionsResolverForThePage(): void
    {
        $registry = new ResolverRegistry(['site.page.home' => new PathResolver()]);

        $this->assertSame('/spa', $registry->resolve(new PreviewRecord('site.page.home', [], ['slug' => 'spa']))->path);
    }

    #[Test]
    public function refusesARecordWithoutAPage(): void
    {
        $resolver = $this->createStub(PreviewResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);

        try {
            (new ResolverRegistry(['site.page.home' => $resolver]))->resolve(
                new PreviewRecord('site.page.home', [], []),
            );
            $this->fail('The record was resolved.');
        } catch (InvalidPreview $refusal) {
            $this->assertSame([404, 'This record has no page on the site to preview yet.'], [
                $refusal->status,
                $refusal->getMessage(),
            ]);
        }
    }

    #[Test]
    public function refusesASectionTheSiteCannotPreview(): void
    {
        $this->expectExceptionObject(InvalidPreview::notPreviewable('This section has no preview on this site.'));

        (new ResolverRegistry([]))->resolve(new PreviewRecord('site.page.home', [], []));
    }
}
