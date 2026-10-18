<?php

declare(strict_types=1);

namespace Contenir\Preview\Container;

use Contenir\Preview\PreviewContext;

/**
 * The request's preview context, shared by every service that reads it.
 */
final class PreviewContextFactory
{
    public function __invoke(): PreviewContext
    {
        return new PreviewContext();
    }
}
