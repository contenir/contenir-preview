<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use Contenir\Preview\PreviewConfig;
use Contenir\SiteToken\Base64Url;
use SensitiveParameter;

use function gmdate;
use function hash_equals;
use function hash_hmac;
use function implode;
use function str_starts_with;

/**
 * The cookie that lets a visitor back into a link once they've entered its code (§9.3): an HMAC of
 * the link's id, code and end under the preview secret, so a new code, a new end or a revoked link
 * turns every pass away. It is sent only to the link's own path and lasts until the link ends.
 * A pass made under any of the site's keys is accepted, so rotating keys turns no one away.
 *
 * @api
 */
final readonly class SharePass
{
    /** The cookie's name. */
    public const string COOKIE = 'contenir_share';

    public function __construct(
        private PreviewConfig $config,
    ) {}

    private static function sign(Share $share, #[SensitiveParameter] string $secret): string
    {
        $mac = hash_hmac(
            'sha256',
            implode('.', [$share->id, $share->code, $share->expires->getTimestamp()]),
            $secret,
            binary: true,
        );

        return Base64Url::encode($mac);
    }

    /**
     * Whether the cookie a visitor sent is a pass for this link as it stands.
     */
    public function accepts(Share $share, ?string $pass): bool
    {
        if (null === $pass || '' === $pass) {
            return false;
        }

        foreach ($this->config->secrets as $secret) {
            if (hash_equals(self::sign($share, $secret), $pass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `Set-Cookie` header that gives a visitor their pass: the link's path only, kept from
     * scripts, sent on top-level visits from other sites (the client following an emailed link),
     * secure where the site is, and ending with the link.
     */
    public function cookie(Share $share): string
    {
        $expires    = gmdate('D, d M Y H:i:s \G\M\T', $share->expires->getTimestamp());
        $attributes = [
            self::COOKIE . "={$this->for($share)}",
            'Path=' . ShareId::PATH . $share->id,
            "Expires={$expires}",
            'HttpOnly',
            'SameSite=Lax',
        ];
        if (str_starts_with($this->config->audience, 'https://')) {
            $attributes[] = 'Secure';
        }

        return implode('; ', $attributes);
    }

    /**
     * The pass for a link, under the key the site signs with.
     */
    public function for(Share $share): string
    {
        return self::sign($share, (string) $this->config->secret($this->config->signWith));
    }
}
