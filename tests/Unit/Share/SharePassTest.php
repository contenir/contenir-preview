<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share;

use Contenir\Preview\PreviewConfig;
use Contenir\Preview\Share\SharePass;
use Contenir\Preview\Tests\TestAsset\Previews;
use Contenir\Preview\Tests\TestAsset\Share\Shares;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The cookie that lets a visitor back into a link once they've entered its code.
 */
#[Group('unit')]
final class SharePassTest extends TestCase
{
    /**
     * @return array<string, array{string|null}>
     */
    public static function passesRefused(): array
    {
        return [
            'none'         => [null],
            'empty'        => [''],
            'made up'      => ['bm90LWEtcGFzcw'],
            'a new code'   => [(new SharePass(Previews::config()))->for(Shares::make(code: 'PQRS23'))],
            'a new end'    => [(new SharePass(Previews::config()))->for(Shares::make(expires: 1_792_236_001))],
            'another link' => [(new SharePass(Previews::config()))->for(Shares::make(id: Shares::idOf('a')))],
            'another key'  => [(new SharePass(self::configSigningWith('2026-11')))->for(Shares::make())],
        ];
    }

    private static function configSigningWith(string $keyId): PreviewConfig
    {
        return new PreviewConfig(
            Previews::ISSUER,
            Previews::AUDIENCE,
            [$keyId => 'another-secret-of-at-least-thirty-two-bytes'],
            $keyId,
        );
    }

    #[Test]
    public function acceptsAPassMadeUnderAKeyBeingRetired(): void
    {
        $rotating = new PreviewConfig(
            Previews::ISSUER,
            Previews::AUDIENCE,
            ['2026-10' => Previews::SHARED, '2026-11' => 'another-secret-of-at-least-thirty-two-bytes'],
            '2026-11',
        );

        $this->assertTrue((new SharePass($rotating))->accepts(
            Shares::make(),
            (new SharePass(Previews::config()))->for(Shares::make()),
        ));
    }

    #[Test]
    public function acceptsItsOwnPass(): void
    {
        $pass = new SharePass(Previews::config());

        $this->assertTrue($pass->accepts(Shares::make(), $pass->for(Shares::make())));
    }

    #[Test]
    public function givesThePassForTheLinksPathUntilItEnds(): void
    {
        $pass = new SharePass(Previews::config());

        $this->assertSame(
            'contenir_share='
                . $pass->for(Shares::make())
                . '; Path=/preview/'
                . Shares::ID
                . '; Expires=Sat, 24 Oct 2026 11:20:00 GMT; HttpOnly; SameSite=Lax; Secure',
            $pass->cookie(Shares::make()),
        );
    }

    #[Test]
    public function leavesThePassUnsecuredOnASiteWithoutHttps(): void
    {
        $plain = new SharePass(new PreviewConfig(
            'http://admin.example.test',
            'http://www.example.test',
            ['2026-10' => Previews::SHARED],
            '2026-10',
        ));

        $this->assertStringEndsWith('; HttpOnly; SameSite=Lax', $plain->cookie(Shares::make()));
    }

    #[Test]
    #[DataProvider('passesRefused')]
    public function refusesAnyOtherPass(?string $cookie): void
    {
        $this->assertFalse((new SharePass(Previews::config()))->accepts(Shares::make(), $cookie));
    }
}
