<?php

declare(strict_types=1);

namespace Contenir\Preview\Clock;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * The system's own time, for a CMS or site with no PSR-20 clock of its own.
 */
final readonly class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
