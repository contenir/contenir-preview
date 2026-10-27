<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Token;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewRecord;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Tests\TestAsset\Tokens;
use Contenir\Preview\Token\TokenVerifier;
use Contenir\SiteToken\Base64Url;
use Contenir\SiteToken\Replay\InMemoryReplayGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;

use function explode;
use function str_repeat;

/**
 * A site checking a posted preview token the way the protocol lays out (§5), and the status and
 * reason it refuses each failure with.
 */
#[Group('unit')]
final class TokenVerifierTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string, string}>
     */
    public static function badRecordProvider(): array
    {
        $record = Tokens::payload()['rec'];

        return [
            'an id not 32 hex digits'    => ['0123', 'jti', 'The preview has no id.'],
            'an id in capitals'          => ['0123456789ABCDEF0123456789ABCDEF', 'jti', 'The preview has no id.'],
            'an id with text before it'  => ['x0123456789abcdef0123456789abcdef', 'jti', 'The preview has no id.'],
            'an id with text after it'   => ['0123456789abcdef0123456789abcdefx', 'jti', 'The preview has no id.'],
            'a section after other text' => [
                ['section' => 'my site.page.home'] + $record,
                'rec',
                'The preview names no section.',
            ],
            'no record'                  => ['site.page.home', 'rec', 'The preview names no record.'],
            'no section'                 => [['section' => null] + $record, 'rec', 'The preview names no section.'],
            'a section of two parts'     => [
                ['section' => 'site.home'] + $record,
                'rec',
                'The preview names no section.',
            ],
            'a section with spaces'      => [
                ['section' => 'site.page.home page'] + $record,
                'rec',
                'The preview names no section.',
            ],
            'a key as a list'            => [
                ['keys' => ['101']] + $record,
                'rec',
                'The preview\'s record key is not a set of columns.',
            ],
            'a key that is a number'     => [
                ['keys' => ['resource_id' => 101]] + $record,
                'rec',
                'The preview\'s record key is not a set of columns.',
            ],
            'no key at all'              => [
                ['keys' => null] + $record,
                'rec',
                'The preview\'s record key is not a set of columns.',
            ],
            'values as a list'           => [
                ['values' => ['a']] + $record,
                'rec',
                'The preview\'s values are not a set of fields.',
            ],
            'a value that is a number'   => [
                ['values' => ['count' => 3]] + $record,
                'rec',
                'The preview\'s values are not a set of fields.',
            ],
            'a value that is an object'  => [
                ['values' => ['tags' => ['a' => 'b']]] + $record,
                'rec',
                'The preview\'s values are not a set of fields.',
            ],
            'a list holding a number'    => [
                ['values' => ['tags' => ['a', 2]]] + $record,
                'rec',
                'The preview\'s values are not a set of fields.',
            ],
            'a column named by number'   => [
                ['keys' => [7 => '101']] + $record,
                'rec',
                'The preview\'s record key is not a set of columns.',
            ],
            'a field named by number'    => [
                ['values' => [3 => 'x']] + $record,
                'rec',
                'The preview\'s values are not a set of fields.',
            ],
        ];
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function malformedProvider(): array
    {
        $valid = Tokens::sign(Tokens::header(), Tokens::payload());

        return [
            'nothing posted'         => [null, 'No preview was sent.'],
            'an empty field'         => ['', 'No preview was sent.'],
            'more than a megabyte'   => [str_repeat('a', times: 1_048_577), 'The preview is too large.'],
            'exactly a megabyte'     => [str_repeat('a', times: 1_048_576), 'The preview is not a preview token.'],
            'two segments'           => ['abc.def', 'The preview is not a preview token.'],
            'four segments'          => ["{$valid}.abc", 'The preview is not a preview token.'],
            'a header not base64url' => [
                'a+b.' . Base64Url::encode('{}') . '.sig',
                'The preview is not a preview token.',
            ],
            'a header not JSON'      => [
                Base64Url::encode('not json') . '.' . Base64Url::encode('{}') . '.sig',
                'The preview is not a preview token.',
            ],
            'a header not an object' => [
                Base64Url::encode('"text"') . '.' . Base64Url::encode('{}') . '.sig',
                'The preview is not a preview token.',
            ],
            'a payload not JSON'     => [
                Base64Url::encode('{}') . '.' . Base64Url::encode('[') . '.sig',
                'The preview is not a preview token.',
            ],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unauthenticatedProvider(): array
    {
        $header  = Tokens::header();
        $payload = Tokens::payload();
        $valid   = Tokens::sign($header, $payload);
        [$h, $p] = explode('.', $valid);

        return [
            'another algorithm'         => [
                Tokens::sign(['alg' => 'none'] + $header, $payload),
                'The preview is not signed the way this site expects.',
            ],
            'another type'              => [
                Tokens::sign(['typ' => 'JWT'] + $header, $payload),
                'The preview is not signed the way this site expects.',
            ],
            'a key this site lacks'     => [
                Tokens::sign(['kid' => '2025-01'] + $header, $payload),
                'The preview is signed with a key this site does not hold.',
            ],
            'no key id'                 => [
                Tokens::sign(['kid' => 7] + $header, $payload),
                'The preview is signed with a key this site does not hold.',
            ],
            'another secret'            => [
                Tokens::sign($header, $payload, 'another-secret-of-at-least-thirty-two-bytes'),
                'The preview\'s signature does not match.',
            ],
            'a signature not base64url' => ["{$h}.{$p}.a+b", 'The preview\'s signature does not match.'],
            'a payload altered'         => [
                $h . '.' . Base64Url::encode('{"ver":1}') . '.' . explode('.', $valid)[2],
                'The preview\'s signature does not match.',
            ],
            'another version'           => [
                Tokens::sign($header, ['ver' => 2] + $payload),
                'The preview uses a protocol version this site does not know.',
            ],
            'another issuer'            => [
                Tokens::sign($header, ['iss' => 'https://elsewhere.test'] + $payload),
                'The preview was not issued for this site.',
            ],
            'another audience'          => [
                Tokens::sign($header, ['aud' => 'https://elsewhere.test'] + $payload),
                'The preview was not issued for this site.',
            ],
            'no issue time'             => [
                Tokens::sign($header, ['iat' => '1792236000'] + $payload),
                'The preview\'s lifetime is not one this site accepts.',
            ],
            'no expiry'                 => [
                Tokens::sign($header, ['exp' => null] + $payload),
                'The preview\'s lifetime is not one this site accepts.',
            ],
            'expiring before issue'     => [
                Tokens::sign($header, ['exp' => 1_792_235_999] + $payload),
                'The preview\'s lifetime is not one this site accepts.',
            ],
            'living over five minutes'  => [
                Tokens::sign($header, ['exp' => 1_792_236_301] + $payload),
                'The preview\'s lifetime is not one this site accepts.',
            ],
            'issued in the future'      => [
                Tokens::sign($header, Tokens::payload(1_792_236_031)),
                'The preview was issued in the future. Check the clocks of the CMS and the site.',
            ],
            'expired'                   => [
                Tokens::sign($header, Tokens::payload(1_792_235_669)),
                'The preview has expired. Refresh the preview to see it again.',
            ],
        ];
    }

    #[Test]
    public function acceptsAnyCaseInASectionsNameAndAKeyOfSeveralColumns(): void
    {
        $payload        = Tokens::payload();
        $payload['rec'] = [
            'section' => 'Site.Page.Home',
            'keys'    => ['resource_id' => '101', 'locale' => 'en'],
            'values'  => [],
        ];

        $record = $this->verifier()->verify(Tokens::sign(Tokens::header(), $payload))->record;

        $this->assertSame(['Site.Page.Home', ['resource_id' => '101', 'locale' => 'en']], [
            $record->section,
            $record->keys,
        ]);
    }

    #[Test]
    public function acceptsARecordNotSavedYetAndAUserItCannotName(): void
    {
        $payload = Tokens::payload();
        unset($payload['sub']);
        $payload['rec'] = ['section' => 'library.news.articles', 'keys' => [], 'values' => []];

        $request = $this->verifier()->verify(Tokens::sign(Tokens::header(), $payload));

        $this->assertSame([true, ''], [$request->record->isNew(), $request->userId]);
    }

    #[Test]
    public function acceptsATokenOnceOnly(): void
    {
        $verifier = $this->verifier();
        $token    = Tokens::sign(Tokens::header(), Tokens::payload());
        $verifier->verify($token);

        $this->expectExceptionObject(InvalidPreview::replayed());

        $verifier->verify($token);
    }

    #[Test]
    public function acceptsATokenThatExpiresAsItIsIssued(): void
    {
        $payload = ['exp' => 1_792_236_000] + Tokens::payload();

        $this->assertSame(
            1_792_236_000,
            $this->verifier()->verify(Tokens::sign(Tokens::header(), $payload))->expiresAt->getTimestamp(),
        );
    }

    #[Test]
    public function allowsTheClocksThirtySecondsEitherWay(): void
    {
        $verifier = $this->verifier();
        $ahead    = Tokens::payload(1_792_236_030);
        $behind   = ['jti' => 'ffffffffffffffffffffffffffffffff'] + Tokens::payload(1_792_235_670);

        $requests = [
            $verifier->verify(Tokens::sign(Tokens::header(), $ahead)),
            $verifier->verify(Tokens::sign(Tokens::header(), $behind)),
        ];

        $this->assertCount(2, $requests);
    }

    #[DataProvider('unauthenticatedProvider')]
    #[Test]
    public function refusesATokenItCannotTrust(#[SensitiveParameter] string $token, string $reason): void
    {
        $this->assertRefused($token, InvalidPreview::UNAUTHENTICATED, $reason);
    }

    #[DataProvider('badRecordProvider')]
    #[Test]
    public function refusesATrustedTokenWhoseContentIsNotTheProtocols(
        mixed $claim,
        string $name,
        string $reason,
    ): void {
        $this->assertRefused(
            Tokens::sign(Tokens::header(), [$name => $claim] + Tokens::payload()),
            InvalidPreview::MALFORMED,
            $reason,
        );
    }

    #[DataProvider('malformedProvider')]
    #[Test]
    public function refusesWhatIsNotAPreviewToken(#[SensitiveParameter] ?string $token, string $reason): void
    {
        $this->assertRefused($token, InvalidPreview::MALFORMED, $reason);
    }

    #[Test]
    public function turnsAValidTokenIntoAPreviewRequest(): void
    {
        $request = $this->verifier()->verify(Tokens::sign(Tokens::header(), Tokens::payload()));

        $this->assertEquals(
            [
                new PreviewRecord(
                    'site.page.home',
                    ['resource_id' => '101'],
                    ['slug' => 'spa-rituals', 'title' => 'Spa Rituals', 'images' => ['401', '402'], 'subtitle' => null],
                ),
                '12',
                Tokens::TOKEN_ID,
                1_792_236_000,
                1_792_236_300,
            ],
            [
                $request->record,
                $request->userId,
                $request->tokenId,
                $request->issuedAt->getTimestamp(),
                $request->expiresAt->getTimestamp(),
            ],
        );
    }

    private function assertRefused(#[SensitiveParameter] ?string $token, int $status, string $reason): void
    {
        try {
            $this->verifier()->verify($token);
            $this->fail('The token was accepted.');
        } catch (InvalidPreview $refusal) {
            $this->assertSame([$status, $reason], [$refusal->status, $refusal->getMessage()]);
        }
    }

    private function verifier(): TokenVerifier
    {
        $clock = new FrozenClock();

        return new TokenVerifier(Tokens::config(), $clock, new InMemoryReplayGuard($clock));
    }
}
