<?php

declare(strict_types=1);

namespace Contenir\Preview;

use RuntimeException;

/**
 * A preview the renderer refuses, with the HTTP status the protocol gives the refusal (§5) and a
 * reason fit to show an editor: it never names the secret, the token or the expected values.
 */
final class InvalidPreview extends RuntimeException
{
    public const int MALFORMED       = 400;
    public const int UNAUTHENTICATED = 401;
    public const int NOT_PREVIEWABLE = 404;
    public const int WRONG_METHOD    = 405;
    public const int REPLAYED        = 409;
    public const int TOO_MANY_TRIES  = 429;

    private function __construct(
        string $reason,
        public readonly int $status,
    ) {
        parent::__construct($reason, $status);
    }

    public static function malformed(string $reason): self
    {
        return new self($reason, self::MALFORMED);
    }

    public static function notPreviewable(string $reason): self
    {
        return new self($reason, self::NOT_PREVIEWABLE);
    }

    public static function replayed(): self
    {
        return new self('This preview has already been shown. Refresh the preview to see it again.', self::REPLAYED);
    }

    /**
     * A link that has ended or been revoked, which says neither, so no one learns which links were real.
     */
    public static function shareGone(): self
    {
        return new self('This preview link has expired or been withdrawn.', self::NOT_PREVIEWABLE);
    }

    public static function tooManyTries(): self
    {
        return new self('Too many tries. Try again in 15 minutes.', self::TOO_MANY_TRIES);
    }

    public static function unauthenticated(string $reason): self
    {
        return new self($reason, self::UNAUTHENTICATED);
    }

    public static function wrongMethod(string $reason = 'A preview is posted, not fetched.'): self
    {
        return new self($reason, self::WRONG_METHOD);
    }
}
