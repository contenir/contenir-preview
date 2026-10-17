<?php

declare(strict_types=1);

namespace Contenir\Preview\Token;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewRecord;

use function is_array;
use function is_string;
use function preg_match;

/**
 * Reads a token's `rec` claim into a PreviewRecord, refusing anything not the shape §3.2 gives:
 * a `module.category.section` name, a key of string columns, and fields (each checked by
 * ClaimValue).
 *
 * @internal
 */
final class RecordClaim
{
    /**
     * @throws InvalidPreview
     */
    public static function parse(mixed $claim): PreviewRecord
    {
        if (! is_array($claim)) {
            throw InvalidPreview::malformed('The preview names no record.');
        }

        /** @var mixed $section */
        $section = $claim['section'] ?? null;
        if (! is_string($section) || 1 !== preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+){2}$/i', $section)) {
            throw InvalidPreview::malformed('The preview names no section.');
        }

        return new PreviewRecord($section, self::keys($claim['keys'] ?? null), self::values($claim['values'] ?? null));
    }

    /**
     * A JSON object's members by name: an array keyed by strings, or an empty one. A JSON list
     * decodes with numbered keys, so it is refused with any object whose members are numbers.
     *
     * @return array<string, mixed>
     * @throws InvalidPreview
     */
    private static function fields(mixed $fields, string $refusal): array
    {
        if (! is_array($fields)) {
            throw InvalidPreview::malformed($refusal);
        }

        $named = [];
        /** @var mixed $value */
        foreach ($fields as $name => $value) {
            if (! is_string($name)) {
                throw InvalidPreview::malformed($refusal);
            }
            $named[$name] = $value;
        }

        return $named;
    }

    /**
     * @return array<string, string>
     * @throws InvalidPreview
     */
    private static function keys(mixed $keys): array
    {
        $valid = [];
        /** @var mixed $value */
        foreach (self::fields($keys, 'The preview\'s record key is not a set of columns.') as $column => $value) {
            if (! is_string($value)) {
                throw InvalidPreview::malformed('The preview\'s record key is not a set of columns.');
            }
            $valid[$column] = $value;
        }

        return $valid;
    }

    /**
     * @return array<string, string|list<string>|null>
     * @throws InvalidPreview
     */
    private static function values(mixed $values): array
    {
        $valid = [];
        /** @var mixed $value */
        foreach (self::fields($values, 'The preview\'s values are not a set of fields.') as $field => $value) {
            $valid[$field] = ClaimValue::parse($value);
        }

        return $valid;
    }
}
