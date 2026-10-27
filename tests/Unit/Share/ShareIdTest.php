<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share;

use Contenir\Preview\Share\ShareId;
use Contenir\Preview\Tests\TestAsset\Share\Shares;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function substr;

/**
 * A link's id, its path and its URL.
 */
#[Group('unit')]
final class ShareIdTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function pathsNotAShare(): array
    {
        return [
            'the preview page'          => ['/preview'],
            'the preview page, slash'   => ['/preview/'],
            'an id one short'           => ['/preview/' . substr(Shares::ID, offset: 0, length: 42)],
            'an id one long'            => ['/preview/' . Shares::ID . 'x'],
            'a trailing slash'          => ['/preview/' . Shares::ID . '/'],
            'a character not base64url' => ['/preview/' . substr(Shares::ID, offset: 0, length: 42) . '+'],
            'below another path'        => ['/en/preview/' . Shares::ID],
            'the editor preview'        => ['/api/resource/preview'],
            'a new line after the id'   => ['/preview/' . Shares::ID . "\n"],
        ];
    }

    #[Test]
    public function findsTheIdInAShareSPath(): void
    {
        $this->assertSame(Shares::ID, ShareId::fromPath('/preview/' . Shares::ID));
    }

    #[Test]
    public function givesALogOnlyTheStartOfAnId(): void
    {
        $this->assertSame(['AbCdEfGh', 'short'], [ShareId::label(Shares::ID), ShareId::label('short')]);
    }

    #[Test]
    public function makesADifferentIdEachTime(): void
    {
        $this->assertNotSame(ShareId::generate(), ShareId::generate());
    }

    #[Test]
    public function makesIdsFindableByTheirPath(): void
    {
        $id = ShareId::generate();

        $this->assertSame($id, ShareId::fromPath("/preview/{$id}"));
    }

    #[Test]
    public function placesTheLinkAtTheSitesOrigin(): void
    {
        $this->assertSame(
            ['https://www.example.test/preview/' . Shares::ID, 'https://www.example.test/preview/' . Shares::ID],
            [
                ShareId::url('https://www.example.test', Shares::ID),
                ShareId::url('https://www.example.test/', Shares::ID),
            ],
        );
    }

    #[Test]
    #[DataProvider('pathsNotAShare')]
    public function takesNoOtherPath(string $path): void
    {
        $this->assertNull(ShareId::fromPath($path));
    }
}
