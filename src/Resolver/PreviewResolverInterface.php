<?php

declare(strict_types=1);

namespace Contenir\Preview\Resolver;

use Contenir\Preview\PreviewRecord;

/**
 * Says which of a site's pages shows a record of one section (§6, step 1), usually from its values.
 *
 * @api
 */
interface PreviewResolverInterface
{
    /**
     * The page, or null where this record has none (a page without an address yet).
     */
    public function resolve(PreviewRecord $record): ?PreviewTarget;
}
