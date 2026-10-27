<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use RuntimeException;
use Throwable;

/**
 * The store of links could not do what it was asked, or holds a row it cannot read.
 *
 * @api
 */
final class ShareStoreException extends RuntimeException
{
    public static function failed(Throwable $failure): self
    {
        return new self("The preview links could not be read or written: {$failure->getMessage()}", previous: $failure);
    }

    public static function unreadable(string $column): self
    {
        return new self("A preview link's {$column} cannot be read.");
    }
}
