<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use Contenir\Preview\Resolver\PreviewTarget;

/**
 * What a framework adapter does with a visit to a link: render the target, show the code form,
 * or set the pass cookie and send the visitor back to the link.
 *
 * @api
 */
final readonly class ShareVisit
{
    /**
     * @param PreviewTarget|null $target The page to render, when the outcome is Show
     * @param string|null        $cookie The `Set-Cookie` header, when the outcome is Admitted
     */
    private function __construct(
        public ShareOutcome $outcome,
        public ?PreviewTarget $target = null,
        public ?string $cookie = null,
    ) {}

    public static function admitted(string $cookie): self
    {
        return new self(ShareOutcome::Admitted, cookie: $cookie);
    }

    public static function askForCode(): self
    {
        return new self(ShareOutcome::AskForCode);
    }

    public static function show(PreviewTarget $target): self
    {
        return new self(ShareOutcome::Show, $target);
    }

    public static function wrongCode(): self
    {
        return new self(ShareOutcome::WrongCode);
    }
}
