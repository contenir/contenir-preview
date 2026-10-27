<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use Contenir\SiteToken\Base64Url;
use Random\Randomizer;

use function preg_match;
use function rtrim;
use function substr;

/**
 * A link's id: 32 random bytes as base64url without padding, 43 characters nobody can guess, and
 * where a site finds it: the path `/preview/{id}`.
 *
 * @api
 */
final class ShareId
{
    /** What a share's path starts with. */
    public const string PATH = '/preview/';

    /** The characters an id has. */
    public const int LENGTH = 43;

    /** How many characters of an id a log may show. */
    public const int LABEL_LENGTH = 8;

    /**
     * The id a share's path carries, or null when the path is not a share's: `/preview/` and then
     * exactly 43 base64url characters, so a site's own `/preview` page still routes.
     */
    public static function fromPath(string $path): ?string
    {
        $match = [];

        return 1 === preg_match('#^/preview/([A-Za-z0-9_-]{43})\z#', $path, $match) ? $match[1] ?? null : null;
    }

    /**
     * A new id, from the system's secure source.
     */
    public static function generate(): string
    {
        return Base64Url::encode((new Randomizer())->getBytes(32));
    }

    /**
     * The start of an id, enough for a log to tell links apart without giving one away.
     */
    public static function label(string $id): string
    {
        return substr($id, offset: 0, length: self::LABEL_LENGTH);
    }

    /**
     * Where a link opens: the site's origin with its id's path.
     */
    public static function url(string $audience, string $id): string
    {
        return rtrim($audience, characters: '/') . self::PATH . $id;
    }
}
