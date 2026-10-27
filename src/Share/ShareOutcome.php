<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

/**
 * What a visit to a link comes to (§9.3), and the status each is answered with.
 *
 * @api
 */
enum ShareOutcome
{
    /** The visitor has a pass: render the record. */
    case Show;

    /** The visitor has no pass yet: show the code form. */
    case AskForCode;

    /** The visitor entered the wrong code: show the code form again, saying so. */
    case WrongCode;

    /** The visitor entered the code: set the pass and send them back to the link. */
    case Admitted;

    public function status(): int
    {
        return match ($this) {
            self::Show, self::AskForCode => 200,
            self::WrongCode => 401,
            self::Admitted => 303,
        };
    }
}
