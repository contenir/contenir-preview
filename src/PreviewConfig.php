<?php

declare(strict_types=1);

namespace Contenir\Preview;

use InvalidArgumentException;

use function array_key_exists;
use function rtrim;
use function sprintf;
use function strlen;

/**
 * The settings the CMS and a site share (§2): who issues tokens and for which site (its base URL),
 * the secrets that sign them by key id, and the one the issuer signs with. A site may hold several
 * keys while rotating.
 */
final readonly class PreviewConfig
{
    /** Where every site answers previews (§4); the protocol fixes it. */
    public const string ENDPOINT = '/api/resource/preview';

    /** Seconds a token lives, at most (§3.2). */
    public const int MAX_LIFETIME = 300;

    /** Seconds either side's clock may be out (§3.2). */
    public const int LEEWAY = 30;

    /** Largest token the renderer decodes, in bytes (§3.3). */
    public const int MAX_TOKEN_BYTES = 1_048_576;

    private const int MIN_SECRET_BYTES = 32;

    /**
     * @param non-empty-array<string, string> $secrets  Key id => secret
     * @param string                          $signWith The key id the issuer signs with
     */
    public function __construct(
        public string $issuer,
        public string $audience,
        public array $secrets,
        public string $signWith,
    ) {
        if (! array_key_exists($signWith, $secrets)) {
            throw new InvalidArgumentException(sprintf('No secret is configured for the key id "%s".', $signWith));
        }

        foreach ($secrets as $keyId => $secret) {
            if (strlen($secret) < self::MIN_SECRET_BYTES) {
                throw new InvalidArgumentException(sprintf(
                    'The secret for the key id "%s" is shorter than %d bytes.',
                    $keyId,
                    self::MIN_SECRET_BYTES,
                ));
            }
        }
    }

    /**
     * The URL the CMS posts previews to: the site's base URL with the protocol's fixed path.
     */
    public function endpoint(): string
    {
        return rtrim($this->audience, characters: '/') . self::ENDPOINT;
    }

    public function secret(string $keyId): ?string
    {
        return $this->secrets[$keyId] ?? null;
    }
}
