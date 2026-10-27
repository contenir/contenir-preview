<?php

declare(strict_types=1);

namespace Contenir\Preview\Container;

use Contenir\SiteToken\Replay\CacheReplayGuard;
use Contenir\SiteToken\Replay\FileReplayGuard;
use Contenir\SiteToken\Replay\ReplayGuardInterface;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

use function sys_get_temp_dir;

/**
 * Where accepted tokens are remembered: the configured PSR-16 cache, which every server shares,
 * or files in a directory (the system's temporary directory unless one is configured).
 */
final class ReplayGuardFactory
{
    /** Starts each accepted preview's file name, as it always has. */
    public const string FILE_PREFIX = 'contenir-preview-';

    /** Starts each accepted preview's cache key, as it always has. */
    public const string CACHE_PREFIX = 'contenir_preview_';

    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration
     */
    public function __invoke(ContainerInterface $container): ReplayGuardInterface
    {
        $options = Service::get($container, PreviewOptions::class);
        $clock   = Service::get($container, ClockInterface::class, ClockFactory::SERVICE);

        return null === $options->replay->cache
            ? new FileReplayGuard($options->replay->directory ?? sys_get_temp_dir(), $clock, self::FILE_PREFIX)
            : new CacheReplayGuard(
                Service::get($container, CacheInterface::class, $options->replay->cache),
                $clock,
                self::CACHE_PREFIX,
            );
    }
}
