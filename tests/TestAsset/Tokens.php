<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\TestAsset;

use Contenir\Preview\PreviewConfig;
use Contenir\SiteToken\Base64Url;
use SensitiveParameter;

use function hash_hmac;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Builds preview tokens for tests: a well-formed one by default, with any header or payload claim
 * replaced or removed, signed with the configured secret unless told otherwise.
 */
final class Tokens
{
    public const string SHARED = 'a-secret-of-at-least-thirty-two-bytes!!';

    public const string ISSUER = 'https://admin.example.test';

    public const string AUDIENCE = 'https://www.example.test';

    public const string TOKEN_ID = '0123456789abcdef0123456789abcdef';

    public static function config(): PreviewConfig
    {
        return new PreviewConfig(self::ISSUER, self::AUDIENCE, ['2026-10' => self::SHARED], '2026-10');
    }

    /**
     * @return array<string, mixed>
     */
    public static function header(): array
    {
        return ['alg' => 'HS256', 'typ' => 'contenir-preview+jwt', 'kid' => '2026-10'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(int $now = 1_792_236_000): array
    {
        return [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => $now,
            'exp' => $now + 300,
            'jti' => self::TOKEN_ID,
            'sub' => '12',
            'ver' => 1,
            'rec' => [
                'section' => 'site.page.home',
                'keys'    => ['resource_id' => '101'],
                'values'  => [
                    'slug'     => 'spa-rituals',
                    'title'    => 'Spa Rituals',
                    'images'   => ['401', '402'],
                    'subtitle' => null,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $payload
     */
    public static function sign(
        array $header,
        array $payload,
        #[SensitiveParameter]
        string $secret = self::SHARED,
    ): string {
        $signed =
            Base64Url::encode(json_encode($header, JSON_THROW_ON_ERROR))
            . '.'
            . Base64Url::encode(json_encode($payload, JSON_THROW_ON_ERROR));

        return $signed . '.' . Base64Url::encode(hash_hmac('sha256', $signed, $secret, binary: true));
    }
}
