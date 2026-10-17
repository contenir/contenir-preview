<?php

declare(strict_types=1);

namespace Contenir\Preview;

use DateTimeImmutable;

/**
 * A verified preview (§6): the record to show, the CMS user who asked, and the token's id and
 * lifetime for the site's logs.
 */
final readonly class PreviewRequest
{
    public function __construct(
        public PreviewRecord $record,
        public string $userId,
        public string $tokenId,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
    ) {}
}
