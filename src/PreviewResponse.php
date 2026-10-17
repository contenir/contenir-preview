<?php

declare(strict_types=1);

namespace Contenir\Preview;

use function htmlspecialchars;
use function sprintf;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * What a renderer adds to every preview it answers (§6, step 4): headers that keep it out of caches
 * and search engines and let only the CMS frame it, and the short page that explains a refusal.
 */
final readonly class PreviewResponse
{
    public function __construct(
        private PreviewConfig $config,
    ) {}

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
            htmlspecialchars($refusal->getMessage(), ENT_QUOTES | ENT_HTML5, encoding: 'UTF-8'),
        );
    }
}
