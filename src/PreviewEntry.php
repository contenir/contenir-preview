<?php

declare(strict_types=1);

namespace Contenir\Preview;

use Contenir\Preview\Resolver\PreviewTarget;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Token\TokenVerifier;
use SensitiveParameter;

use function strtoupper;

/**
 * What every renderer does with a request to its preview endpoint, whatever its framework: accept
 * only a post, verify the token, find the page that shows the record, and begin the preview context
 * (§4 to §6). The adapter then renders the page it is given.
 */
final readonly class PreviewEntry
{
    public const string FIELD = 'contenir_preview';

    public function __construct(
        private TokenVerifier $verifier,
        private ResolverRegistry $resolvers,
        private PreviewContext $context,
    ) {}

    /**
     * @param string      $method The request's HTTP method
     * @param string|null $token  The posted `contenir_preview` field
     * @throws InvalidPreview
     */
    public function open(string $method, #[SensitiveParameter] ?string $token): PreviewTarget
    {
        if ('POST' !== strtoupper($method)) {
            throw InvalidPreview::wrongMethod();
        }

        $request = $this->verifier->verify($token);
        $target  = $this->resolvers->resolve($request->record);
        $this->context->begin($request);

        return $target;
    }
}
