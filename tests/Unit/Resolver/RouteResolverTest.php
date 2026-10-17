<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Resolver;

use Contenir\Preview\PreviewRecord;
use Contenir\Preview\Resolver\RouteResolver;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A record's page as one of the site's named routes, its parameters filled from the record.
 */
#[Group('unit')]
final class RouteResolverTest extends TestCase
{
    #[Test]
    public function fillsTheRoutesParametersFromTheValues(): void
    {
        $target = (new RouteResolver('news/article', ['year' => '{year}', 'slug' => '{slug}', 'page' => 'p-{page}']))
            ->resolve(
                new PreviewRecord('library.news.articles', [], ['year' => '2026', 'slug' => 'winter', 'page' => ['1']]),
            );

        $this->assertSame(
            [true, 'news/article', ['year' => '2026', 'slug' => 'winter', 'page' => 'p-']],
            [$target->isRoute(), $target->route, $target->params],
        );
    }

    #[Test]
    public function namesARouteWithoutParameters(): void
    {
        $this->assertSame(
            [],
            (new RouteResolver('home'))->resolve(new PreviewRecord('site.page.home', [], []))->params,
        );
    }
}
