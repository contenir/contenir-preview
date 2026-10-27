<?php

declare(strict_types=1);

namespace Contenir\Preview\Container;

use Contenir\Preview\Share\PhpDbShareStore;
use Contenir\Preview\Share\ShareStoreInterface;
use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * The links, in the database `contenir_preview.share.adapter` names.
 */
final class ShareStoreFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration When the site has no shared previews
     */
    public function __invoke(ContainerInterface $container): ShareStoreInterface
    {
        $share = Service::get($container, PreviewOptions::class)->share
            ?? throw new InvalidConfiguration(
                'Shared previews are off: the configuration has no contenir_preview.share.',
            );

        return new PhpDbShareStore(Service::get($container, AdapterInterface::class, $share->adapter));
    }
}
