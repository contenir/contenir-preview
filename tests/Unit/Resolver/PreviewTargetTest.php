<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Resolver;

use Contenir\Preview\Resolver\PreviewTarget;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A previewed record's page: a path the site serves, or one of its named routes.
 */
#[Group('unit')]
final class PreviewTargetTest extends TestCase
{
    #[Test]
    public function holdsANamedRouteWithItsParameters(): void
    {
        $target = PreviewTarget::route('news/article', ['slug' => 'winter']);

        $this->assertSame([false, null, 'news/article', ['slug' => 'winter']], [
            $target->isPath(),
            $target->path,
            $target->route,
            $target->params,
        ]);
    }

    #[Test]
    public function holdsANamedRouteWithoutParameters(): void
    {
        $this->assertSame([], PreviewTarget::route('home')->params);
    }

    #[Test]
    public function holdsAPathFromTheSitesRoot(): void
    {
        $target = PreviewTarget::path('about/us');

        $this->assertSame([true, '/about/us', null, []], [
            $target->isPath(),
            $target->path,
            $target->route,
            $target->params,
        ]);
    }
}
