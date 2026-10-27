<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use Random\Randomizer;

use function hash_equals;
use function preg_replace;
use function strtoupper;

/**
 * The code a visitor enters to open a link: six characters with no look-alikes (no 0 or O, no 1,
 * I or L), about 887 million codes. Entry ignores case, spaces and dashes.
 *
 * @api
 */
final class ShareCode
{
    /** The characters a code is made of. */
    public const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** The characters a code has. */
    public const int LENGTH = 6;

    /**
     * A new code, from the system's secure source.
     */
    public static function generate(): string
    {
        return (new Randomizer())->getBytesFromString(self::ALPHABET, self::LENGTH);
    }

    /**
     * Whether what a visitor entered is the code, compared in constant time.
     */
    public static function matches(string $code, string $entered): bool
    {
        return hash_equals($code, self::normalise($entered));
    }

    /**
     * What a visitor entered, as a code is written: capitals, without spaces or dashes.
     */
    private static function normalise(string $entered): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/u', replacement: '', subject: $entered));
    }
}
