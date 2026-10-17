<?php

declare(strict_types=1);

namespace Contenir\Preview\Token;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewConfig;
use JsonException;
use SensitiveParameter;

use function count;
use function explode;
use function is_array;
use function json_decode;
use function strlen;

use const JSON_THROW_ON_ERROR;

/**
 * A posted token taken apart (§5, steps 1 and 2): its header and payload decoded, the text its
 * signature covers, and the signature's bytes. Nothing in it is trusted until the signature is.
 *
 * @internal
 */
final readonly class ParsedToken
{
    /**
     * @param array<array-key, mixed> $header
     * @param array<array-key, mixed> $payload
     */
    private function __construct(
        public array $header,
        public array $payload,
        public string $signed,
        public ?string $signature,
    ) {}

    /**
     * @throws InvalidPreview
     */
    public static function parse(#[SensitiveParameter] ?string $token): self
    {
        if (null === $token || '' === $token) {
            throw InvalidPreview::malformed('No preview was sent.');
        }
        if (strlen($token) > PreviewConfig::MAX_TOKEN_BYTES) {
            throw InvalidPreview::malformed('The preview is too large.');
        }

        $segments = explode('.', $token);
        if (3 !== count($segments)) {
            throw InvalidPreview::malformed('The preview is not a preview token.');
        }
        $header    = $segments[0];
        $payload   = $segments[1] ?? '';
        $signature = $segments[2] ?? '';

        return new self(
            self::decode($header),
            self::decode($payload),
            "{$header}.{$payload}",
            Base64Url::decode($signature),
        );
    }

    /**
     * @return array<array-key, mixed>
     * @throws InvalidPreview
     */
    private static function decode(string $segment): array
    {
        $json = Base64Url::decode($segment);
        if (null === $json) {
            throw InvalidPreview::malformed('The preview is not a preview token.');
        }

        try {
            /** @var mixed $data */
            $data = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidPreview::malformed('The preview is not a preview token.');
        }

        if (! is_array($data)) {
            throw InvalidPreview::malformed('The preview is not a preview token.');
        }

        return $data;
    }
}
