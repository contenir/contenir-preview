<?php

declare(strict_types=1);

namespace Contenir\Preview;

use function array_key_exists;
use function count;

/**
 * The record a preview shows (§3.2 `rec`): its section as the CMS names it
 * (`module.category.section`), its primary key (empty for a record not saved yet) and its fields
 * as the CMS's edit form holds them, saved or not.
 */
final readonly class PreviewRecord
{
    /**
     * @param array<string, string>                   $keys   Primary key column => value
     * @param array<string, string|list<string>|null> $values Field name => value (§3.3)
     */
    public function __construct(
        public string $section,
        public array $keys,
        public array $values,
    ) {}

    /**
     * Whether this is the record a site has loaded: the same section and primary key, compared as
     * strings, since the CMS sends every key as one.
     *
     * @param array<string, scalar|null> $keys
     */
    public function is(string $section, array $keys): bool
    {
        if ($section !== $this->section || [] === $this->keys || count($keys) !== count($this->keys)) {
            return false;
        }

        foreach ($this->keys as $column => $value) {
            if (! array_key_exists($column, $keys) || (string) $keys[$column] !== $value) {
                return false;
            }
        }

        return true;
    }

    public function isNew(): bool
    {
        return [] === $this->keys;
    }

    /**
     * A field's value, or null where the token carries none.
     *
     * @return string|list<string>|null
     */
    public function value(string $field): string|array|null
    {
        return $this->values[$field] ?? null;
    }
}
