<?php

declare(strict_types=1);

namespace Contenir\Preview\Token;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewConfig;
use Contenir\Preview\PreviewRequest;
use Contenir\SiteToken\Refusal;
use Contenir\SiteToken\RefusedToken;
use Contenir\SiteToken\Replay\ReplayGuardInterface;
use Contenir\SiteToken\TokenVerifier as SiteTokenVerifier;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * A site's side: checks a posted preview token the way §5 lays out, in that order, and turns it
 * into a PreviewRequest, or refuses it with the status the protocol gives each failure.
 * contenir-site-token checks the token itself (steps 1 to 6); this checks the record (step 7) and
 * words each refusal for an editor.
 */
final readonly class TokenVerifier
{
    private SiteTokenVerifier $verifier;

    public function __construct(
        PreviewConfig $config,
        ClockInterface $clock,
        ReplayGuardInterface $replay,
    ) {
        $this->verifier = new SiteTokenVerifier($config->keys, $clock, $replay);
    }

    /**
     * The protocol's own words for each refusal (§5), with its status.
     */
    private static function refusal(Refusal $refusal): InvalidPreview
    {
        return match ($refusal) {
            Refusal::Missing        => InvalidPreview::malformed('No preview was sent.'),
            Refusal::TooLarge       => InvalidPreview::malformed('The preview is too large.'),
            Refusal::Malformed      => InvalidPreview::malformed('The preview is not a preview token.'),
            Refusal::NoTokenId      => InvalidPreview::malformed('The preview has no id.'),
            Refusal::WrongSigning => InvalidPreview::unauthenticated(
                'The preview is not signed the way this site expects.',
            ),
            Refusal::UnknownKey => InvalidPreview::unauthenticated(
                'The preview is signed with a key this site does not hold.',
            ),
            Refusal::BadSignature   => InvalidPreview::unauthenticated('The preview\'s signature does not match.'),
            Refusal::WrongVersion => InvalidPreview::unauthenticated(
                'The preview uses a protocol version this site does not know.',
            ),
            Refusal::WrongParties   => InvalidPreview::unauthenticated('The preview was not issued for this site.'),
            Refusal::BadLifetime => InvalidPreview::unauthenticated(
                'The preview\'s lifetime is not one this site accepts.',
            ),
            Refusal::IssuedInFuture => InvalidPreview::unauthenticated(
                'The preview was issued in the future. Check the clocks of the CMS and the site.',
            ),
            Refusal::Expired => InvalidPreview::unauthenticated(
                'The preview has expired. Refresh the preview to see it again.',
            ),
            Refusal::Replayed       => InvalidPreview::replayed(),
        };
    }

    /**
     * @throws InvalidPreview
     */
    public function verify(#[SensitiveParameter] ?string $token): PreviewRequest
    {
        try {
            $verified = $this->verifier->verify(PreviewConfig::tokenType(), $token);
        } catch (RefusedToken $refused) {
            throw self::refusal($refused->refusal);
        }

        return new PreviewRequest(
            RecordClaim::parse($verified->claims['rec'] ?? null),
            $verified->subject,
            $verified->tokenId,
            $verified->issuedAt,
            $verified->expiresAt,
        );
    }
}
