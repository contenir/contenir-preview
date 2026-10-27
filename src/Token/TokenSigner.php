<?php

declare(strict_types=1);

namespace Contenir\Preview\Token;

use Closure;
use Contenir\Preview\PreviewConfig;
use Contenir\Preview\PreviewRecord;
use Contenir\SiteToken\TokenSigner as SiteTokenSigner;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

/**
 * The CMS's side: signs a preview token (§3) for a record and the user previewing it, living
 * PreviewConfig::MAX_LIFETIME seconds and carrying a fresh one-time id. contenir-site-token signs
 * it; this adds the record (`rec`).
 */
final readonly class TokenSigner
{
    public const string TYPE = PreviewConfig::TYPE;

    public const int VERSION = PreviewConfig::VERSION;

    private SiteTokenSigner $signer;

    /**
     * @param (Closure(): string)|null $tokenId Makes each token's id; 128 random bits as hex by default
     */
    public function __construct(
        PreviewConfig $config,
        ClockInterface $clock,
        ?Closure $tokenId = null,
    ) {
        $this->signer = new SiteTokenSigner($config->keys, $clock, $tokenId);
    }

    /**
     * @throws InvalidArgumentException Never: `rec` is not one of the shared claims.
     */
    public function sign(PreviewRecord $record, string $userId): string
    {
        return $this->signer->sign(PreviewConfig::tokenType(), $userId, [
            'rec' => [
                'section' => $record->section,
                'keys'    => (object) $record->keys,
                'values'  => (object) $record->values,
            ],
        ]);
    }
}
