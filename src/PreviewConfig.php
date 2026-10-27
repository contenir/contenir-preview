<?php

declare(strict_types=1);

namespace Contenir\Preview;

use Contenir\SiteToken\SiteKeys;
use Contenir\SiteToken\TokenType;
use InvalidArgumentException;

use function rtrim;

/**
 * The settings the CMS and a site share (§2): who issues tokens and for which site (both origins),
 * the secrets that sign them by key id, and the one the issuer signs with. A site may hold several
 * keys while rotating. contenir-site-token's SiteKeys checks them.
 *
 * Each side is named by an origin and nothing more. The issuer is sent as the preview's
 * `frame-ancestors` (§6), where a browser matches the framing document's origin, so a source
 * carrying a path matches nothing and every preview would render as a blank frame; the audience
 * has the protocol's fixed endpoint path appended.
 */
final readonly class PreviewConfig
{
    /** Where every site answers previews (§4); the protocol fixes it. */
    public const string ENDPOINT = '/api/resource/preview';

    /** The `typ` of a preview token's header (§3.1). */
    public const string TYPE = 'contenir-preview+jwt';

    /** The protocol's version, as a token's `ver` carries it (§3.2). */
    public const int VERSION = 1;

    /** Seconds a token lives, at most (§3.2). */
    public const int MAX_LIFETIME = 300;

    /** Seconds either side's clock may be out (§3.2). */
    public const int LEEWAY = TokenType::LEEWAY;

    /** Largest token the renderer decodes, in bytes (§3.3). */
    public const int MAX_TOKEN_BYTES = 1_048_576;

    /** The keys both sides hold, as contenir-site-token checks them. */
    public SiteKeys $keys;

    /**
     * @param non-empty-array<string, string> $secrets  Key id => secret
     * @param string                          $signWith The key id the issuer signs with
     *
     * @throws InvalidArgumentException If a setting cannot be used as it stands.
     */
    public function __construct(
        public string $issuer,
        public string $audience,
        public array $secrets,
        public string $signWith,
    ) {
        $this->keys = new SiteKeys($issuer, $audience, $secrets, $signWith);
    }

    /**
     * The preview protocol's tokens, as contenir-site-token signs and checks them.
     *
     * @throws InvalidArgumentException Never: the protocol's bounds are all positive.
     */
    public static function tokenType(): TokenType
    {
        return new TokenType(self::TYPE, self::VERSION, self::MAX_LIFETIME, self::MAX_TOKEN_BYTES);
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
        return $this->keys->secret($keyId);
    }
}
