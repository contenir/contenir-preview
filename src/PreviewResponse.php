<?php

declare(strict_types=1);

namespace Contenir\Preview;

use Contenir\Preview\Share\ShareEntry;

use function htmlspecialchars;
use function sprintf;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * What a renderer adds to every preview it answers (§6, step 4): headers that keep it out of caches
 * and search engines and let only the CMS frame it, and the short page that explains a refusal.
 * A shared preview (§9.4) is shown on its own, so no one may frame it, and asks for its code with
 * a page of the same kind.
 */
final readonly class PreviewResponse
{
    public function __construct(
        private PreviewConfig $config,
    ) {}

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, encoding: 'UTF-8');
    }

    private static function form(string $alert): string
    {
        return sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>Preview</title></head>'
                . '<body style="font: 15px/1.5 system-ui, sans-serif; margin: 2rem; color: #333">'
                . '<h1 style="font-size: 1.1rem">Preview</h1>'
                . '<form method="post"><label for="code">Enter the code you were given</label><br>'
                . '<input id="code" name="%s" required autofocus autocomplete="one-time-code" autocapitalize="characters"'
                . ' spellcheck="false" maxlength="12" style="font: inherit; letter-spacing: .2em; margin: .5rem .5rem 0 0">'
                . '<button type="submit" style="font: inherit">View</button>%s</form></body></html>',
            ShareEntry::FIELD,
            $alert,
        );
    }

    /**
     * The page that asks a visitor for a link's code. It posts back to the link it was shown at.
     */
    public function codeForm(): string
    {
        return self::form('');
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'Cache-Control'           => 'no-store, private',
            'X-Robots-Tag'            => 'noindex, nofollow',
            'Referrer-Policy'         => 'no-referrer',
            'Content-Security-Policy' => "frame-ancestors {$this->config->issuer}",
        ];
    }

    public function refusal(InvalidPreview $refusal): string
    {
        return sprintf(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
                . '<title>Preview unavailable</title></head>'
                . '<body style="font: 15px/1.5 system-ui, sans-serif; margin: 2rem; color: #333">'
                . '<h1 style="font-size: 1.1rem">Preview unavailable</h1><p>%s</p></body></html>',
            self::escape($refusal->getMessage()),
        );
    }

    /**
     * The headers of every answer to a link: its page, its code form and its refusals.
     *
     * @return array<string, string>
     */
    public function shareHeaders(): array
    {
        return [...$this->headers(), 'Content-Security-Policy' => "frame-ancestors 'none'"];
    }

    /**
     * The code form again, saying the code entered was wrong.
     */
    public function wrongCodeForm(): string
    {
        return self::form('<p role="alert" style="color: #b00020">That code isn&apos;t right.</p>');
    }
}
