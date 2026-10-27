<?php

declare(strict_types=1);

namespace Contenir\Preview;

use DateTimeImmutable;

/**
 * A preview a site is rendering (§6): the record to show, the CMS user who asked, and the token's
 * id and lifetime for the site's logs. A shared preview (§9) carries its link's user, the start of
 * its id and its lifetime instead, and says it is shared, so the site can tell a client's visit
 * from an editor's preview (to leave it out of analytics, say).
 */
final readonly class PreviewRequest
{
    /**
     * @mago-expect lint:excessive-parameter-list The record, who asked, and the token's id, lifetime and kind.
     */
    public function __construct(
        public PreviewRecord $record,
        public string $userId,
        public string $tokenId,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public bool $shared = false,
    ) {}
}
