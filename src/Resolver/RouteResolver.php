<?php

declare(strict_types=1);

namespace Contenir\Preview\Resolver;

use Contenir\Preview\PreviewRecord;
use Override;

use function is_string;
use function preg_replace_callback;

/**
 * Resolves a record to one of the site's named routes, each parameter filled from a pattern of the
 * record's values: `['slug' => '{slug}']`. A value the token lacks, or holds as a list, is empty.
 */
final readonly class RouteResolver implements PreviewResolverInterface
{
    /**
     * @param array<string, string> $params Parameter => pattern of `{field}` placeholders
     */
    public function __construct(
        private string $route,
        private array $params = [],
    ) {}

    #[Override]
    public function resolve(PreviewRecord $record): PreviewTarget
    {
        $params = [];
        foreach ($this->params as $name => $pattern) {
            $params[$name] = (string) preg_replace_callback(
                '/\{([A-Za-z0-9_]+)\}/',
                static function (array $match) use ($record): string {
                    $value = $record->value($match[1] ?? '');

                    return is_string($value) ? $value : '';
                },
                $pattern,
            );
        }

        return PreviewTarget::route($this->route, $params);
    }
}
