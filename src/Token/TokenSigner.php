<?php

declare(strict_types=1);

namespace Contenir\Preview\Token;

use Closure;
use Contenir\Preview\PreviewConfig;
use Contenir\Preview\PreviewRecord;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

use function bin2hex;
use function hash_hmac;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The CMS's side: signs a preview token (§3) for a record and the user previewing it, living
 * PreviewConfig::MAX_LIFETIME seconds and carrying a fresh one-time id.
 */
final readonly class TokenSigner
{
    public const string TYPE = 'contenir-preview+jwt';

    public const int VERSION = 1;

    /** @var Closure(): string */
    private Closure $tokenId;

    /**
     * @param (Closure(): string)|null $tokenId Makes each token's id; 128 random bits as hex by default
     */
    public function __construct(
        private PreviewConfig $config,
        private ClockInterface $clock,
        ?Closure $tokenId = null,
    ) {
        $this->tokenId = $tokenId ?? self::randomId(...);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 128 bits from the system's secure source, as hex.
     */
    private static function randomId(): string
    {
        return bin2hex((new Randomizer())->getBytes(16));
    }

    public function sign(PreviewRecord $record, string $userId): string
    {
        $issuedAt = $this->clock->now()->getTimestamp();
        $header   = Base64Url::encode(self::json([
            'alg' => 'HS256',
            'typ' => self::TYPE,
            'kid' => $this->config->signWith,
        ]));
        $payload = Base64Url::encode(self::json([
            'iss' => $this->config->issuer,
            'aud' => $this->config->audience,
            'iat' => $issuedAt,
            'exp' => $issuedAt + PreviewConfig::MAX_LIFETIME,
            'jti' => ($this->tokenId)(),
            'sub' => $userId,
            'ver' => self::VERSION,
            'rec' => [
                'section' => $record->section,
                'keys'    => (object) $record->keys,
                'values'  => (object) $record->values,
            ],
        ]));
        $secret    = (string) $this->config->secret($this->config->signWith);
        $signature = Base64Url::encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, binary: true));

        return "{$header}.{$payload}.{$signature}";
    }
}
