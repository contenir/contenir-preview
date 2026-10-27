<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

use function array_filter;
use function array_values;
use function mb_strlen;
use function trim;

/**
 * The CMS's side of links (§9): makes a link to a saved record with a fresh id and code, lasting
 * the period the editor picked and no longer than LONGEST; lists a record's live links; gives one
 * a new code; revokes one; and forgets them when their record goes or they end.
 *
 * @api
 */
final readonly class Shares
{
    /** The longest a link may last, in seconds: 90 days. */
    public const int LONGEST = 90 * 86_400;

    /** The longest note a link may carry, in characters. */
    public const int NOTE_LENGTH = 255;

    /** @var Closure(): string */
    private Closure $ids;

    /** @var Closure(): string */
    private Closure $codes;

    /**
     * @param (Closure(): string)|null $ids   Makes each link's id; ShareId::generate() by default
     * @param (Closure(): string)|null $codes Makes each code; ShareCode::generate() by default
     */
    public function __construct(
        private ShareStoreInterface $store,
        private ClockInterface $clock,
        ?Closure $ids = null,
        ?Closure $codes = null,
    ) {
        $this->ids   = $ids ?? ShareId::generate(...);
        $this->codes = $codes ?? ShareCode::generate(...);
    }

    /**
     * A time without its fraction of a second, as the store keeps it.
     */
    private static function toTheSecond(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTime((int) $time->format('G'), (int) $time->format('i'), (int) $time->format('s'));
    }

    /**
     * @param string                $section   The record's section, `module.category.section`
     * @param array<string, string> $keys      Its primary key; a record not saved yet can't be shared
     * @param DateTimeImmutable     $ends      When the link stops opening
     * @param string|null           $note      Who the link is for
     * @param string                $createdBy The editor making it
     *
     * @throws InvalidArgumentException When the record has no key, the note is too long, or the link would end in the past or after LONGEST.
     * @throws ShareStoreException
     */
    public function create(
        string $section,
        array $keys,
        DateTimeImmutable $ends,
        ?string $note,
        string $createdBy,
    ): Share {
        if ('' === $section || [] === $keys) {
            throw new InvalidArgumentException('Only a saved record can be shared.');
        }

        $now     = self::toTheSecond($this->clock->now());
        $ends    = self::toTheSecond($ends);
        $seconds = $ends->getTimestamp() - $now->getTimestamp();
        if ($seconds < 1 || $seconds > self::LONGEST) {
            throw new InvalidArgumentException('A link must end in the next 90 days.');
        }

        $note = null === $note ? '' : trim($note);
        if (mb_strlen($note) > self::NOTE_LENGTH) {
            throw new InvalidArgumentException('A link\'s note can be at most 255 characters.');
        }

        $share = new Share(
            ($this->ids)(),
            ($this->codes)(),
            $section,
            $keys,
            '' === $note ? null : $note,
            $createdBy,
            $now,
            $ends,
        );
        $this->store->add($share);

        return $share;
    }

    /**
     * A link by its id, while it's live.
     *
     * @throws ShareStoreException
     */
    public function find(string $id): ?Share
    {
        $share = $this->store->find($id);

        return null !== $share && $share->isLiveAt($this->clock->now()) ? $share : null;
    }

    /**
     * Deletes every link to a record, as when the record is deleted.
     *
     * @param array<string, string> $keys
     *
     * @throws ShareStoreException
     */
    public function forgetRecord(string $section, array $keys): int
    {
        return $this->store->deleteForRecord($section, $keys);
    }

    /**
     * A record's live links, ending soonest first.
     *
     * @param array<string, string> $keys
     *
     * @return list<Share>
     *
     * @throws ShareStoreException
     */
    public function forRecord(string $section, array $keys): array
    {
        $now = $this->clock->now();

        return array_values(array_filter(
            $this->store->forRecord($section, $keys),
            static fn(Share $share): bool => $share->isLiveAt($now),
        ));
    }

    /**
     * Gives a live link a new code, so the old one and every pass made with it stop working.
     *
     * @throws ShareStoreException
     */
    public function newCode(string $id): ?Share
    {
        $share = $this->find($id);
        if (null === $share) {
            return null;
        }

        $share = $share->withCode(($this->codes)());

        return $this->store->replaceCode($id, $share->code) ? $share : null;
    }

    /**
     * Deletes the links that have ended.
     *
     * @throws ShareStoreException
     */
    public function prune(): int
    {
        return $this->store->deleteExpired($this->clock->now());
    }

    /**
     * Deletes a link, so it stops opening at once.
     *
     * @throws ShareStoreException
     */
    public function revoke(string $id): bool
    {
        return $this->store->delete($id);
    }
}
