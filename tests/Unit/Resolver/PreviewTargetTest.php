<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Resolver;

use Contenir\Preview\Resolver\PreviewTarget;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A previewed record's page: a path the site serves, one of its named routes, or a handler it
 * dispatches directly.
 */
#[Group('unit')]
final class PreviewTargetTest extends TestCase
{
    #[Test]
    public function holdsAHandlerWithWhatItIsDispatchedWith(): void
    {
        $target = PreviewTarget::handler('page-controller', [
            'action'      => 'index',
            'resource_id' => ['resource_id' => '101'],
        ]);

        $this->assertSame(
            [
                false,
                false,
                true,
                null,
                null,
                'page-controller',
                ['action' => 'index', 'resource_id' => ['resource_id' => '101']],
            ],
            [
                $target->isPath(),
                $target->isRoute(),
                $target->isHandler(),
                $target->path,
                $target->route,
                $target->handler,
                $target->params,
            ],
        );
    }

    #[Test]
    public function holdsANamedRouteWithItsParameters(): void
    {
        $target = PreviewTarget::route('news/article', ['slug' => 'winter']);

        $this->assertSame(
            [false, true, false, null, 'news/article', null, ['slug' => 'winter']],
            [
                $target->isPath(),
                $target->isRoute(),
                $target->isHandler(),
                $target->path,
                $target->route,
                $target->handler,
                $target->params,
            ],
        );
    }

    #[Test]
    public function holdsAPathFromTheSitesRoot(): void
    {
        $target = PreviewTarget::path('about/us');

        $this->assertSame(
            [true, false, false, '/about/us', null, null, []],
            [
                $target->isPath(),
                $target->isRoute(),
                $target->isHandler(),
                $target->path,
                $target->route,
                $target->handler,
                $target->params,
            ],
        );
    }

    #[Test]
    public function holdsARouteOrHandlerWithoutParameters(): void
    {
        $this->assertSame([[], []], [PreviewTarget::route('home')->params, PreviewTarget::handler('home')->params]);
    }
}
