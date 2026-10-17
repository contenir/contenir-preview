<?php

declare(strict_types=1);

namespace Contenir\Preview\Resolver;

use Contenir\Preview\PreviewRecord;
use Override;

use function array_map;
use function explode;
use function implode;
use function is_string;
use function preg_replace;
use function preg_replace_callback;
use function rawurlencode;
use function trim;

/**
 * Resolves a record to a path made from a pattern and its values: `/{slug}` for a site page whose
 * slug holds its whole address, `/news/{slug}` for an article. Each value is URL-encoded segment by
 * segment, so a slug's own slashes stay path separators; a value the token lacks counts as empty.
 */
final readonly class PathResolver implements PreviewResolverInterface
{
    public function __construct(
        private string $pattern = '/{slug}',
    ) {}

    #[Override]
    public function resolve(PreviewRecord $record): PreviewTarget
    {
        $path = (string) preg_replace_callback(
            '/\{([A-Za-z0-9_]+)\}/',
            static function (array $match) use ($record): string {
                $value = $record->value($match[1] ?? '');

                return (
                    is_string($value)
                        ? implode(
                            separator: '/',
                            array: array_map(rawurlencode(...), explode(
                                separator: '/',
                                string: trim($value, characters: '/'),
                            )),
                        )
                        : ''
                );
            },
            $this->pattern,
        );

        return PreviewTarget::path((string) preg_replace('#/{2,}#', replacement: '/', subject: $path));
    }
}
