<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewRequest;
use Contenir\Preview\Resolver\PathResolver;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Share\ShareEntry;
use Contenir\Preview\Share\ShareOutcome;
use Contenir\Preview\Share\SharePass;
use Contenir\Preview\Share\Throttle\ShareThrottle;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Tests\TestAsset\Previews;
use Contenir\Preview\Tests\TestAsset\Share\ArrayCounter;
use Contenir\Preview\Tests\TestAsset\Share\InMemoryShareStore;
use Contenir\Preview\Tests\TestAsset\Share\Shares;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A visit to a link taken as far as what to answer: the record, the code form, or the pass.
 */
#[Group('unit')]
final class ShareEntryTest extends TestCase
{
    private const string VISITOR = '203.0.113.7';

    private InMemoryShareStore $store;

    private FrozenClock $clock;

    private PreviewContext $context;

    private SharePass $pass;

    private ShareThrottle $throttle;

    /**
     * @return array<string, array{string}>
     */
    public static function methodsRefused(): array
    {
        return ['a put' => ['PUT'], 'a head' => ['HEAD'], 'a delete' => ['DELETE']];
    }

    #[Test]
    public function admitsAVisitorWhoEntersTheCode(): void
    {
        $visit = $this->entry()->open('POST', Shares::ID, null, 'k7m 4qx', self::VISITOR);

        $this->assertSame(
            [ShareOutcome::Admitted, $this->pass->cookie(Shares::make()), false],
            [$visit->outcome, $visit->cookie, $this->context->isActive()],
        );
    }

    #[Test]
    public function asksAgainWhenThePassIsForAnOldCode(): void
    {
        $old = $this->pass->for(Shares::make(code: 'OLD000'));

        $this->assertSame(
            ShareOutcome::AskForCode,
            $this->entry()->open('GET', Shares::ID, $old, null, self::VISITOR)->outcome,
        );
    }

    #[Test]
    public function asksAVisitorWithoutAPassForTheCode(): void
    {
        $visit = $this->entry()->open('get', Shares::ID, null, null, self::VISITOR);

        $this->assertSame([ShareOutcome::AskForCode, false], [$visit->outcome, $this->context->isActive()]);
    }

    #[Test]
    public function countsAWrongCodeAndSaysSo(): void
    {
        $visit = $this->entry()->open('POST', Shares::ID, null, 'WRONG1', self::VISITOR);
        for ($tries = 1; $tries < ShareThrottle::PER_VISITOR; $tries++) {
            $this->entry()->open('POST', Shares::ID, null, null, self::VISITOR);
        }

        $this->assertSame(
            [ShareOutcome::WrongCode, true],
            [$visit->outcome, $this->throttle->isBlocked(Shares::ID, self::VISITOR)],
        );
    }

    #[Test]
    public function refusesALinkThatHasEnded(): void
    {
        $this->clock->advance(Shares::WEEK);

        $this->expectExceptionObject(InvalidPreview::shareGone());

        $this->entry()->open('GET', Shares::ID, $this->pass->for(Shares::make()), null, self::VISITOR);
    }

    #[Test]
    public function refusesALinkThatIsGone(): void
    {
        $this->store->delete(Shares::ID);

        $this->expectExceptionObject(InvalidPreview::shareGone());

        $this->entry()->open('GET', Shares::ID, $this->pass->for(Shares::make()), null, self::VISITOR);
    }

    #[Test]
    #[DataProvider('methodsRefused')]
    public function refusesAnyMethodButGetAndPost(string $method): void
    {
        try {
            $this->entry()->open($method, Shares::ID, null, null, self::VISITOR);
            $this->fail('The link was opened.');
        } catch (InvalidPreview $refusal) {
            $this->assertSame(
                [405, 'A shared preview is opened, or its code sent, and nothing else.'],
                [$refusal->status, $refusal->getMessage()],
            );
        }
    }

    #[Test]
    public function refusesEvenTheRightCodeOnceAVisitorHasTriedTooOften(): void
    {
        for ($tries = 0; $tries < ShareThrottle::PER_VISITOR; $tries++) {
            $this->throttle->fail(Shares::ID, self::VISITOR);
        }

        try {
            $this->entry()->open('POST', Shares::ID, null, Shares::CODE, self::VISITOR);
            $this->fail('The code was checked.');
        } catch (InvalidPreview $refusal) {
            $this->assertSame([429, 'Too many tries. Try again in 15 minutes.'], [
                $refusal->status,
                $refusal->getMessage(),
            ]);
        }
    }

    #[Test]
    public function showsTheSavedRecordToAVisitorWithAPass(): void
    {
        $visit = $this->entry()->open('GET', Shares::ID, $this->pass->for(Shares::make()), null, self::VISITOR);

        $this->assertEquals(
            [
                ShareOutcome::Show,
                '/spa-rituals',
                new PreviewRequest(
                    Shares::make()->record(),
                    '12',
                    'AbCdEfGh',
                    (new DateTimeImmutable())->setTimestamp(Shares::CREATED),
                    (new DateTimeImmutable())->setTimestamp(Shares::CREATED + Shares::WEEK),
                    shared: true,
                ),
            ],
            [$visit->outcome, $visit->target?->path, $this->context->request()],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->store    = new InMemoryShareStore();
        $this->clock    = new FrozenClock(Shares::CREATED);
        $this->context  = new PreviewContext();
        $this->pass     = new SharePass(Previews::config());
        $this->throttle = new ShareThrottle(new ArrayCounter(), $this->clock);
        $this->store->add(Shares::make());
    }

    private function entry(): ShareEntry
    {
        return new ShareEntry(
            $this->store,
            new ResolverRegistry(['site.page.home' => new PathResolver('/spa-rituals')]),
            $this->context,
            $this->pass,
            $this->throttle,
            $this->clock,
        );
    }
}
