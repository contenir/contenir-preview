<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use Contenir\Preview\PreviewRecord;
use DateTimeImmutable;

/**
 * A link an editor made to one record's draft (§9): its id, the code a visitor enters, the record
 * it shows, who it's for and who made it, and when it ends. It shows the record as it's saved, so
 * it carries no values of its own.
 *
 * @api
 */
final readonly class Share
{
    /**
     * @param string                $id        The link's id, as its path carries it
     * @param string                $code      The code a visitor enters, as ShareCode makes it
     * @param string                $section   The record's section, `module.category.section`
     * @param array<string, string> $keys      The record's primary key, column => value
     * @param string|null           $note      Who the link is for, as the editor put it
     * @param string                $createdBy The CMS user who made the link
     *
     * @mago-expect lint:excessive-parameter-list One parameter per column of a preview_share row.
     */
    public function __construct(
        public string $id,
        public string $code,
        public string $section,
        public array $keys,
        public ?string $note,
        public string $createdBy,
        public DateTimeImmutable $created,
        public DateTimeImmutable $expires,
    ) {}

    /**
     * Whether the link still opens at $now: it opens until the moment it ends.
     */
    public function isLiveAt(DateTimeImmutable $now): bool
    {
        return $now < $this->expires;
    }

    /**
     * The record to show, as it's saved: no values stand in for the stored ones.
     */
    public function record(): PreviewRecord
    {
        return new PreviewRecord($this->section, $this->keys, []);
    }

    public function withCode(string $code): self
    {
        return new self(
            $this->id,
            $code,
            $this->section,
            $this->keys,
            $this->note,
            $this->createdBy,
            $this->created,
            $this->expires,
        );
    }
}
