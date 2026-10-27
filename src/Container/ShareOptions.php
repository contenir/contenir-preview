<?php

declare(strict_types=1);

namespace Contenir\Preview\Container;

use PhpDb\Adapter\AdapterInterface;

use function is_array;

/**
 * Shared previews (`contenir_preview.share`), which a site turns on by naming the block, empty or
 * not: the php-db adapter on the database the CMS keeps links in, the site's own by default.
 * Wrong codes are counted where accepted tokens are remembered (`contenir_preview.replay`).
 */
final readonly class ShareOptions
{
    private function __construct(
        public string $adapter,
    ) {}

    /**
     * The block's settings, or null when the site has no shared previews.
     *
     * @throws InvalidConfiguration
     */
    public static function from(mixed $share): ?self
    {
        if (null === $share) {
            return null;
        }

        if (! is_array($share)) {
            throw new InvalidConfiguration('contenir_preview.share must be an array.');
        }

        return new self(
            Setting::optionalText($share, 'adapter', 'contenir_preview.share.adapter') ?? AdapterInterface::class,
        );
    }
}
