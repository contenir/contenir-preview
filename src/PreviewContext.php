<?php

declare(strict_types=1);

namespace Contenir\Preview;

use LogicException;

/**
 * The preview a site is rendering, for the rest of the request (§6, step 2). The site's data layer
 * asks it to lay the token's values over a record it has loaded, or for the values of a record not
 * saved yet; outside a preview both leave the site's data as it is.
 */
final class PreviewContext
{
    private ?PreviewRequest $request = null;

    /**
     * @throws LogicException When a preview has begun already
     */
    public function begin(PreviewRequest $request): void
    {
        if (null !== $this->request) {
            throw new LogicException('A preview has already begun in this request.');
        }

        $this->request = $request;
    }

    public function isActive(): bool
    {
        return null !== $this->request;
    }

    /**
     * A record the site has loaded, with the preview's values in place of its own where it is the
     * previewed record, and as it is otherwise.
     *
     * @param array<string, scalar|null> $keys   The loaded record's primary key
     * @param array<string, mixed>       $stored The loaded record's columns
     * @return array<string, mixed>
     */
    public function overlay(string $section, array $keys, array $stored): array
    {
        $record = $this->request?->record;

        return null !== $record && $record->is($section, $keys) ? [...$stored, ...$record->values] : $stored;
    }

    public function request(): ?PreviewRequest
    {
        return $this->request;
    }

    /**
     * The values of the previewed record when it is one of the section's not saved yet, which no
     * stored record can stand in for.
     *
     * @return array<string, string|list<string>|null>|null
     */
    public function unsaved(string $section): ?array
    {
        $record = $this->request?->record;

        return null !== $record && $record->isNew() && $record->section === $section ? $record->values : null;
    }
}
