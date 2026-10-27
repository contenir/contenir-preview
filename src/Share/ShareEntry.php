<?php

declare(strict_types=1);

namespace Contenir\Preview\Share;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewRequest;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Share\Throttle\ShareThrottle;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

use function strtoupper;

/**
 * What every renderer does with a visit to a link, whatever its framework (§9.3): find the link,
 * and then show the record to a visitor holding a pass, ask one without a pass for the code, or
 * check the code they post. The adapter renders what it is told to.
 *
 * @api
 */
final readonly class ShareEntry
{
    /** The posted field that carries the code. */
    public const string FIELD = 'code';

    /**
     * @mago-expect lint:excessive-parameter-list The link, its record's page and preview, the pass, the throttle and the time.
     */
    public function __construct(
        private ShareStoreInterface $store,
        private ResolverRegistry $resolvers,
        private PreviewContext $context,
        private SharePass $pass,
        private ShareThrottle $throttle,
        private ClockInterface $clock,
    ) {}

    /**
     * @param string      $method  The request's HTTP method
     * @param string      $id      The link's id, as ShareId::fromPath() found it
     * @param string|null $pass    The pass cookie the visitor sent
     * @param string|null $code    The code the visitor posted
     * @param string      $visitor Who is visiting, for the throttle: their address
     *
     * @throws InvalidPreview When the method is neither GET nor POST, the link is gone, the visitor has
     *                        entered too many wrong codes, or the record has no preview on this site.
     * @throws ShareStoreException
     */
    public function open(
        string $method,
        string $id,
        #[SensitiveParameter]
        ?string $pass,
        #[SensitiveParameter]
        ?string $code,
        string $visitor,
    ): ShareVisit {
        $method = strtoupper($method);
        if ('GET' !== $method && 'POST' !== $method) {
            throw InvalidPreview::wrongMethod('A shared preview is opened, or its code sent, and nothing else.');
        }

        $share = $this->store->find($id);
        if (null === $share || ! $share->isLiveAt($this->clock->now())) {
            throw InvalidPreview::shareGone();
        }

        if ('POST' === $method) {
            return $this->check($share, $code ?? '', $visitor);
        }

        if (! $this->pass->accepts($share, $pass)) {
            return ShareVisit::askForCode();
        }

        $record = $share->record();
        $target = $this->resolvers->resolve($record);
        $this->context->begin(new PreviewRequest(
            $record,
            $share->createdBy,
            ShareId::label($share->id),
            $share->created,
            $share->expires,
            shared: true,
        ));

        return ShareVisit::show($target);
    }

    /**
     * @throws InvalidPreview When the visitor has entered too many wrong codes.
     */
    private function check(Share $share, #[SensitiveParameter] string $code, string $visitor): ShareVisit
    {
        if ($this->throttle->isBlocked($share->id, $visitor)) {
            throw InvalidPreview::tooManyTries();
        }

        if (ShareCode::matches($share->code, $code)) {
            return ShareVisit::admitted($this->pass->cookie($share));
        }

        $this->throttle->fail($share->id, $visitor);

        return ShareVisit::wrongCode();
    }
}
