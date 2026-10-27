<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;

use function array_is_list;
use function gmdate;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * A link as a `preview_share` row and back: times in UTC, and the record's key as JSON with its
 * columns in order, so one record's links are found by comparing text.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity Each column is checked as it is read.
 */
final class ShareRow
{
    private const string TIME = 'Y-m-d H:i:s';

    /**
     * A record's key as the row keeps it.
     *
     * @param array<string, string> $keys
     *
     * @throws ShareStoreException
     */
    public static function keys(array $keys): string
    {
        ksort($keys);

        try {
            return json_encode((object) $keys, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $failure) {
            throw ShareStoreException::failed($failure);
        }
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @throws ShareStoreException When a column cannot be read
     */
    public static function share(array $row): Share
    {
        /** @var mixed $note */
        $note = $row['note'] ?? null;

        return new Share(
            self::text($row, 'preview_share_id'),
            self::text($row, 'code'),
            self::text($row, 'section'),
            self::decodeKeys($row['record_keys'] ?? null),
            is_string($note) && '' !== $note ? $note : null,
            self::text($row, 'created_by'),
            self::readTime($row, 'created'),
            self::readTime($row, 'expires'),
        );
    }

    /**
     * A time as the row keeps it.
     */
    public static function time(DateTimeImmutable $time): string
    {
        return gmdate(self::TIME, $time->getTimestamp());
    }

    /**
     * @return array<string, string|null>
     *
     * @throws ShareStoreException
     */
    public static function values(Share $share): array
    {
        return [
            'preview_share_id' => $share->id,
            'code'             => $share->code,
            'section'          => $share->section,
            'record_keys'      => self::keys($share->keys),
            'note'             => $share->note,
            'created_by'       => $share->createdBy,
            'created'          => self::time($share->created),
            'expires'          => self::time($share->expires),
        ];
    }

    /**
     * @return array<string, string>
     *
     * @throws ShareStoreException
     */
    private static function decodeKeys(mixed $json): array
    {
        try {
            /** @var mixed $keys */
            $keys = is_string($json) ? json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            throw ShareStoreException::unreadable('record_keys');
        }

        if (! is_array($keys) || ([] !== $keys && array_is_list($keys))) {
            throw ShareStoreException::unreadable('record_keys');
        }

        $valid = [];
        /** @var mixed $value */
        foreach ($keys as $column => $value) {
            $valid[(string) $column] = is_string($value)
                ? $value
                : throw ShareStoreException::unreadable('record_keys');
        }

        return $valid;
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @throws ShareStoreException
     */
    private static function readTime(array $row, string $column): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable(self::text($row, $column), new DateTimeZone('UTC'));
        } catch (Exception) {
            throw ShareStoreException::unreadable($column);
        }
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @throws ShareStoreException
     */
    private static function text(array $row, string $column): string
    {
        /** @var mixed $value */
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) ? $value : throw ShareStoreException::unreadable($column);
    }
}
