<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewResponse;
use Contenir\Preview\Tests\TestAsset\Tokens;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What every preview answer carries, and the page that explains a refusal.
 */
#[Group('unit')]
final class PreviewResponseTest extends TestCase
{
    #[Test]
    public function explainsARefusalInAPageItEscapes(): void
    {
        $page = (new PreviewResponse(Tokens::config()))->refusal(InvalidPreview::malformed('A <b>bad</b> "token"'));

        $this->assertSame(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
                . '<title>Preview unavailable</title></head>'
                . '<body style="font: 15px/1.5 system-ui, sans-serif; margin: 2rem; color: #333">'
                . '<h1 style="font-size: 1.1rem">Preview unavailable</h1><p>A &lt;b&gt;bad&lt;/b&gt; &quot;token&quot;</p></body></html>',
            $page,
        );
    }

    #[Test]
    public function keepsAPreviewOutOfCachesAndSearchAndFramedByTheCmsOnly(): void
    {
        $this->assertSame(
            [
                'Cache-Control'           => 'no-store, private',
                'X-Robots-Tag'            => 'noindex, nofollow',
                'Referrer-Policy'         => 'no-referrer',
                'Content-Security-Policy' => 'frame-ancestors https://admin.example.test',
            ],
            (new PreviewResponse(Tokens::config()))->headers(),
        );
    }
}
