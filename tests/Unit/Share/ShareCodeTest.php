<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share;

use Contenir\Preview\Share\ShareCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * The code a visitor enters, made without look-alikes and matched however it was typed.
 */
#[Group('unit')]
final class ShareCodeTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function waysOfTypingTheCode(): array
    {
        return [
            'as given'          => ['K7M4QX'],
            'small letters'     => ['k7m4qx'],
            'spaced'            => [' K7M 4QX '],
            'dashed'            => ['K7M-4QX'],
            'tabbed and dashed' => ["K7M\t-\t4QX"],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function wrongCodes(): array
    {
        return [
            'nothing'           => [''],
            'one character off' => ['K7M4QY'],
            'too short'         => ['K7M4Q'],
            'too long'          => ['K7M4QXX'],
            'a look-alike'      => ['K7M4OX'],
        ];
    }

    #[Test]
    public function makesADifferentCodeEachTime(): void
    {
        $codes = [];
        for ($made = 0; $made < 20; $made++) {
            $codes[ShareCode::generate()] = true;
        }

        $this->assertGreaterThan(1, count($codes));
    }

    #[Test]
    public function makesCodesOfSixCharactersWithNoLookAlikes(): void
    {
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{6}$/', ShareCode::generate());
    }

    #[Test]
    #[DataProvider('waysOfTypingTheCode')]
    public function matchesTheCodeHoweverItWasTyped(string $entered): void
    {
        $this->assertTrue(ShareCode::matches('K7M4QX', $entered));
    }

    #[Test]
    #[DataProvider('wrongCodes')]
    public function refusesAnyOtherCode(string $entered): void
    {
        $this->assertFalse(ShareCode::matches('K7M4QX', $entered));
    }
}
