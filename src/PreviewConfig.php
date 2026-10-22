<?php

declare(strict_types=1);

namespace Contenir\Preview;

use InvalidArgumentException;

use function array_key_exists;
use function is_array;
use function parse_url;
use function rtrim;
use function sprintf;
use function strlen;

/**
 * The settings the CMS and a site share (§2): who issues tokens and for which site (both origins),
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
     * @throws InvalidArgumentException If a setting cannot be used as it stands.
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

        self::checkOrigin('issuer', $issuer);
        self::checkOrigin('audience', $audience);
    }

    /**
     * Each side is named by an origin and nothing more. The issuer is sent as the preview's
     * `frame-ancestors` (§6), where a browser matches the framing document's origin, so a source
     * carrying a path matches nothing and every preview renders as a blank frame; the audience has
     * the protocol's fixed endpoint path appended. Both are also matched whole against a token's
     * `iss` and `aud`, so each must be the one string both sides hold.
     *
     * @throws InvalidArgumentException If $value carries anything beyond a scheme, host and port.
     */
    private static function checkOrigin(string $setting, string $value): void
    {
        $url    = parse_url($value);
        $parts  = is_array($url) ? $url : [];
        $scheme = $parts['scheme'] ?? null;
        $host   = $parts['host'] ?? null;
        $port   = $parts['port'] ?? null;

        if (null !== $scheme && null !== $host) {
            $origin = null === $port ? "{$scheme}://{$host}" : "{$scheme}://{$host}:{$port}";
            if ($value === $origin || $value === "{$origin}/") {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'The %s "%s" must be an origin: a scheme, a host and an optional port, and nothing else.',
            $setting,
            $value,
        ));
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
