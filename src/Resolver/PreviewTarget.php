<?php

declare(strict_types=1);

namespace Contenir\Preview\Resolver;

use function ltrim;

/**
 * Which of a site's pages shows a previewed record: a path the site would serve, or one of its
 * named routes with parameters. Each framework adapter dispatches whichever the site's resolver
 * gives.
 */
final readonly class PreviewTarget
{
    /**
     * @param array<string, string> $params
     */
    private function __construct(
        public ?string $path,
        public ?string $route,
        public array $params,
    ) {}

    public static function path(string $path): self
    {
        return new self('/' . ltrim($path, characters: '/'), null, []);
    }

    /**
     * @param array<string, string> $params
     */
    public static function route(string $name, array $params = []): self
    {
        return new self(null, $name, $params);
    }

    public function isPath(): bool
    {
        return null !== $this->path;
    }
}
