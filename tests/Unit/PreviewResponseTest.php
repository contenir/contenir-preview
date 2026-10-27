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
    public function asksAgainSayingTheCodeWasWrong(): void
    {
        $this->assertStringEndsWith(
            '<button type="submit" style="font: inherit">View</button>'
                . '<p role="alert" style="color: #b00020">That code isn&apos;t right.</p></form></body></html>',
            (new PreviewResponse(Tokens::config()))->wrongCodeForm(),
        );
    }

    #[Test]
    public function asksForALinksCode(): void
    {
        $this->assertSame(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>Preview</title></head>'
                . '<body style="font: 15px/1.5 system-ui, sans-serif; margin: 2rem; color: #333">'
                . '<h1 style="font-size: 1.1rem">Preview</h1>'
                . '<form method="post"><label for="code">Enter the code you were given</label><br>'
                . '<input id="code" name="code" required autofocus autocomplete="one-time-code" autocapitalize="characters"'
                . ' spellcheck="false" maxlength="12" style="font: inherit; letter-spacing: .2em; margin: .5rem .5rem 0 0">'
                . '<button type="submit" style="font: inherit">View</button></form></body></html>',
            (new PreviewResponse(Tokens::config()))->codeForm(),
        );
    }

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

    #[Test]
    public function keepsASharedPreviewOutOfCachesAndSearchAndUnframed(): void
    {
        $this->assertSame(
            [
                'Cache-Control'           => 'no-store, private',
                'X-Robots-Tag'            => 'noindex, nofollow',
                'Referrer-Policy'         => 'no-referrer',
                'Content-Security-Policy' => "frame-ancestors 'none'",
            ],
            (new PreviewResponse(Tokens::config()))->shareHeaders(),
        );
    }
}
