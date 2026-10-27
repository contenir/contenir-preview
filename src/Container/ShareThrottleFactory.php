<?php

declare(strict_types=1);

namespace Contenir\Preview\Container;

use Contenir\Preview\Share\Throttle\CacheCounter;
use Contenir\Preview\Share\Throttle\FileCounter;
use Contenir\Preview\Share\Throttle\ShareThrottle;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;

use function sys_get_temp_dir;

/**
 * Counts wrong codes where accepted tokens are remembered: the configured PSR-16 cache, which
 * every server shares, or files in a directory (the system's temporary directory unless one is
 * configured), under prefixes of their own.
 */
final class ShareThrottleFactory
{
    /** Starts each count's file name. */
    public const string FILE_PREFIX = 'contenir-share-';

    /** Starts each count's cache key. */
    public const string CACHE_PREFIX = 'contenir_share_';

    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration
     */
    public function __invoke(ContainerInterface $container): ShareThrottle
    {
        $options = Service::get($container, PreviewOptions::class);
        $clock   = Service::get($container, ClockInterface::class, ClockFactory::SERVICE);

        return new ShareThrottle(
            null === $options->replay->cache
                ? new FileCounter($options->replay->directory ?? sys_get_temp_dir(), self::FILE_PREFIX)
                : new CacheCounter(
                    Service::get($container, CacheInterface::class, $options->replay->cache),
                    $clock,
                    self::CACHE_PREFIX,
                ),
            $clock,
        );
    }
}
