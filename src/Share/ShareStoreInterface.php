<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use DateTimeImmutable;

/**
 * Where links are kept. The CMS adds, changes and deletes them; a site only finds them.
 *
 * @api
 */
interface ShareStoreInterface
{
    /**
     * @throws ShareStoreException
     */
    public function add(Share $share): void;

    /**
     * Deletes a link, so it stops opening at once. False when there was none.
     *
     * @throws ShareStoreException
     */
    public function delete(string $id): bool;

    /**
     * Deletes the links that ended by $now.
     *
     * @return int How many were deleted
     *
     * @throws ShareStoreException
     */
    public function deleteExpired(DateTimeImmutable $now): int;

    /**
     * Deletes every link to one record, as when the record is deleted.
     *
     * @param array<string, string> $keys
     *
     * @return int How many were deleted
     *
     * @throws ShareStoreException
     */
    public function deleteForRecord(string $section, array $keys): int;

    /**
     * @throws ShareStoreException
     */
    public function find(string $id): ?Share;

    /**
     * Every link to one record, ending soonest first, whether or not it has ended.
     *
     * @param array<string, string> $keys
     *
     * @return list<Share>
     *
     * @throws ShareStoreException
     */
    public function forRecord(string $section, array $keys): array;

    /**
     * Gives a link a new code. False when there was no such link.
     *
     * @throws ShareStoreException
     */
    public function replaceCode(string $id, string $code): bool;
}
