<?php

declare(strict_types=1);

namespace Contenir\Preview\Resolver;

use function ltrim;

/**
 * Which of a site's pages shows a previewed record: a path the site would serve, one of its named
 * routes with parameters, or a handler the site dispatches directly with parameters, for a page its
 * router does not know (a draft, a record not saved yet). Each framework adapter dispatches
 * whichever the site's resolver gives.
 */
final readonly class PreviewTarget
{
    /**
     * @param array<string, mixed> $params
     */
    private function __construct(
        public ?string $path,
        public ?string $route,
        public ?string $handler,
        public array $params,
    ) {}

    /**
     * @param string               $handler The framework's name for it: a controller, a middleware service
     * @param array<string, mixed> $params  What it is dispatched with, as a route would give them
     */
    public static function handler(string $handler, array $params = []): self
    {
        return new self(null, null, $handler, $params);
    }

    public static function path(string $path): self
    {
        return new self('/' . ltrim($path, characters: '/'), null, null, []);
    }

    /**
     * @param array<string, string> $params
     */
    public static function route(string $name, array $params = []): self
    {
        return new self(null, $name, null, $params);
    }

    public function isHandler(): bool
    {
        return null !== $this->handler;
    }

    public function isPath(): bool
    {
        return null !== $this->path;
    }

    public function isRoute(): bool
    {
        return null !== $this->route;
    }
}
