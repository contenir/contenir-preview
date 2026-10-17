<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Resolver;

use Contenir\Preview\PreviewRecord;
use Contenir\Preview\Resolver\PathResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A record's page as a path made from a pattern and its values.
 */
#[Group('unit')]
final class PathResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, array<string, string|list<string>|null>, string}>
     */
    public static function pathProvider(): array
    {
        return [
            'a page at its slug'             => ['/{slug}', ['slug' => 'spa-rituals'], '/spa-rituals'],
            'a nested slug'                  => ['/{slug}', ['slug' => 'about/our-story'], '/about/our-story'],
            'a slug with slashes either end' => ['/{slug}', ['slug' => '/about/'], '/about'],
            'the home page'                  => ['/{slug}', ['slug' => ''], '/'],
            'a slug the token lacks'         => ['/{slug}', [], '/'],
            'a slug that is a list'          => ['/{slug}', ['slug' => ['a', 'b']], '/'],
            'characters a path cannot hold'  => [
                '/{slug}',
                ['slug' => 'spa & café/ä b'],
                '/spa%20%26%20caf%C3%A9/%C3%A4%20b',
            ],
            'an article under news'          => [
                '/news/{year}/{slug}',
                ['year' => '2026', 'slug' => 'winter'],
                '/news/2026/winter',
            ],
            'an empty part between'          => ['/news/{year}/{slug}', ['slug' => 'winter'], '/news/winter'],
            'a pattern without a lead slash' => ['{slug}', ['slug' => 'spa'], '/spa'],
        ];
    }

    /**
     * @param array<string, string|list<string>|null> $values
     */
    #[DataProvider('pathProvider')]
    #[Test]
    public function makesThePathFromThePatternAndTheValues(string $pattern, array $values, string $path): void
    {
        $target = (new PathResolver($pattern))->resolve(
            new PreviewRecord('site.page.home', ['resource_id' => '1'], $values),
        );

        $this->assertSame([true, $path], [$target->isPath(), $target->path]);
    }

    #[Test]
    public function resolvesASitePageAtItsSlugByDefault(): void
    {
        $this->assertSame(
            '/contact',
            (new PathResolver())->resolve(new PreviewRecord('site.page.home', [], ['slug' => 'contact']))->path,
        );
    }
}
