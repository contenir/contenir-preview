<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Token;

use Contenir\Preview\PreviewRecord;
use Contenir\Preview\Replay\InMemoryReplayGuard;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Tests\TestAsset\Tokens;
use Contenir\Preview\Token\Base64Url;
use Contenir\Preview\Token\TokenSigner;
use Contenir\Preview\Token\TokenVerifier;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

use function explode;
use function json_decode;
use function preg_match;
use function str_contains;

/**
 * The CMS signing a preview token (§3) that a site holding the same secret accepts.
 */
#[Group('unit')]
final class TokenSignerTest extends TestCase
{
    #[Test]
    public function givesEachTokenA128BitRandomIdByDefault(): void
    {
        $signer = new TokenSigner(Tokens::config(), new FrozenClock());
        $record = new PreviewRecord('site.page.home', ['resource_id' => '101'], []);
        $first  = $this->segment($signer->sign($record, '12'), 1)['jti'];
        $second = $this->segment($signer->sign($record, '12'), 1)['jti'];

        $this->assertSame([1, 1, true], [
            preg_match('/^[0-9a-f]{32}$/', $first),
            preg_match('/^[0-9a-f]{32}$/', $second),
            $first !== $second,
        ]);
    }

    #[Test]
    public function signsATokenTheSiteAccepts(): void
    {
        $clock    = new FrozenClock();
        $verifier = new TokenVerifier(Tokens::config(), $clock, new InMemoryReplayGuard($clock));

        $this->assertSame(Tokens::TOKEN_ID, $verifier->verify($this->sign())->tokenId);
    }

    #[Test]
    public function signsTheProtocolsHeader(): void
    {
        $this->assertSame(
            ['alg' => 'HS256', 'typ' => 'contenir-preview+jwt', 'kid' => '2026-10'],
            $this->segment($this->sign(), 0),
        );
    }

    #[Test]
    public function signsTheRecordAndUserForFiveMinutes(): void
    {
        $this->assertSame(
            [
                'iss' => Tokens::ISSUER,
                'aud' => Tokens::AUDIENCE,
                'iat' => 1_792_236_000,
                'exp' => 1_792_236_300,
                'jti' => Tokens::TOKEN_ID,
                'sub' => '12',
                'ver' => 1,
                'rec' => [
                    'section' => 'site.page.home',
                    'keys'    => ['resource_id' => '101'],
                    'values'  => ['slug' => 'spa/rituals', 'title' => 'Spa “Rituals”'],
                ],
            ],
            $this->segment($this->sign(), 1),
        );
    }

    #[Test]
    public function writesSlashesAndTextAsTheyAreAndAnUnsavedRecordsFieldsAsObjects(): void
    {
        $saved   = (string) Base64Url::decode(explode('.', $this->sign())[1]);
        $unsaved = (string) Base64Url::decode(
            explode('.', $this->signer()->sign(new PreviewRecord('library.news.articles', [], []), '12'))[1],
        );

        $this->assertSame(
            [true, true, true],
            [
                str_contains($saved, '"slug":"spa/rituals"'),
                str_contains($saved, '"title":"Spa “Rituals”"'),
                str_contains($unsaved, '"keys":{},"values":{}'),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function segment(#[SensitiveParameter] string $token, int $index): array
    {
        return json_decode((string) Base64Url::decode(explode('.', $token)[$index]), associative: true);
    }

    private function sign(): string
    {
        return $this->signer()->sign(
            new PreviewRecord(
                'site.page.home',
                ['resource_id' => '101'],
                ['slug' => 'spa/rituals', 'title' => 'Spa “Rituals”'],
            ),
            '12',
        );
    }

    private function signer(): TokenSigner
    {
        return new TokenSigner(Tokens::config(), new FrozenClock(), static fn(): string => Tokens::TOKEN_ID);
    }
}
