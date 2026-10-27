<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\TestAsset\Share;

use Contenir\Preview\Share\Share;
use DateTimeImmutable;

use function str_repeat;

/**
 * Links for tests: one to the home page, made at the frozen clock's instant and lasting a week,
 * with any of its parts replaced.
 */
final class Shares
{
    public const string ID = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-_AbCdE';

    public const string CODE = 'K7M4QX';

    public const int CREATED = 1_792_236_000;

    public const int WEEK = 7 * 86_400;

    /**
     * An id of 43 of one character, for a link that has to differ from another.
     */
    public static function idOf(string $character): string
    {
        return str_repeat($character, times: 43);
    }

    /**
     * @param array<string, string> $keys
     *
     * @mago-expect lint:excessive-parameter-list Any part of a link may be replaced.
     */
    public static function make(
        string $id = self::ID,
        string $code = self::CODE,
        string $section = 'site.page.home',
        array $keys = ['resource_id' => '101'],
        ?string $note = 'Jane, Acme',
        int $expires = self::CREATED + self::WEEK,
    ): Share {
        return new Share(
            $id,
            $code,
            $section,
            $keys,
            $note,
            '12',
            (new DateTimeImmutable())->setTimestamp(self::CREATED),
            (new DateTimeImmutable())->setTimestamp($expires),
        );
    }
}
