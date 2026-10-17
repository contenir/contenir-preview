<?php

declare(strict_types=1);

namespace Contenir\Preview\Resolver;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewRecord;

/**
 * The resolvers a site registers, one per section it can preview.
 */
final readonly class ResolverRegistry
{
    /**
     * @param array<string, PreviewResolverInterface> $resolvers Section (`module.category.section`) => resolver
     */
    public function __construct(
        private array $resolvers,
    ) {}

    /**
     * @throws InvalidPreview When the site has no preview for the record's section, or this record
     */
    public function resolve(PreviewRecord $record): PreviewTarget
    {
        $resolver = $this->resolvers[$record->section] ?? null;
        if (null === $resolver) {
            throw InvalidPreview::notPreviewable('This section has no preview on this site.');
        }

        return (
            $resolver->resolve($record)
                ?? throw InvalidPreview::notPreviewable('This record has no page on the site to preview yet.')
        );
    }
}
