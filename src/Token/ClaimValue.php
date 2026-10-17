<?php

declare(strict_types=1);

namespace Contenir\Preview\Token;

use Contenir\Preview\InvalidPreview;

use function array_is_list;
use function is_array;
use function is_string;

/**
 * One field's value in a token's `rec` claim (§3.3): a string, a null, or a list of strings.
 *
 * @internal
 */
final class ClaimValue
{
    /**
     * @return string|list<string>|null
     * @throws InvalidPreview
     */
    public static function parse(mixed $value): string|array|null
    {
        if (null === $value || is_string($value)) {
            return $value;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidPreview::malformed('The preview\'s values are not a set of fields.');
        }

        $items = [];
        /** @var mixed $item */
        foreach ($value as $item) {
            if (! is_string($item)) {
                throw InvalidPreview::malformed('The preview\'s values are not a set of fields.');
            }
            $items[] = $item;
        }

        return $items;
    }
}
