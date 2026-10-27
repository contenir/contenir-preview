<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share;

use Contenir\Preview\Share\Share;
use Contenir\Preview\Share\Shares;
use Contenir\Preview\Share\ShareStoreInterface;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Tests\TestAsset\Share\InMemoryShareStore;
use Contenir\Preview\Tests\TestAsset\Share\Shares as ShareFixtures;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function array_shift;
use function str_repeat;

/**
 * The CMS's side of links: making, listing, re-coding, revoking and forgetting them.
 */
#[Group('unit')]
final class SharesTest extends TestCase
{
    private const int NOW = ShareFixtures::CREATED;

    private InMemoryShareStore $store;

    private FrozenClock $clock;

    private Shares $shares;

    /**
     * @return array<string, array{array<string, string>, int, string|null, string}>
     */
    public static function linksRefused(): array
    {
        return [
            'a record not saved'   => [[], 3600, null, 'Only a saved record can be shared.'],
            'ending now'           => [['resource_id' => '101'], 0, null, 'A link must end in the next 90 days.'],
            'ending in the past'   => [['resource_id' => '101'], -60, null, 'A link must end in the next 90 days.'],
            'ending after 90 days' => [
                ['resource_id' => '101'],
                Shares::LONGEST + 1,
                null,
                'A link must end in the next 90 days.',
            ],
            'a note too long'      => [
                ['resource_id' => '101'],
                3600,
                str_repeat('é', times: 256),
                'A link\'s note can be at most 255 characters.',
            ],
        ];
    }

    private static function at(int $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable())->setTimestamp($timestamp);
    }

    #[Test]
    public function findsALiveLink(): void
    {
        $this->store->add(ShareFixtures::make());

        $this->assertEquals(ShareFixtures::make(), $this->shares->find(ShareFixtures::ID));
    }

    #[Test]
    public function findsNoLinkThatHasEnded(): void
    {
        $this->store->add(ShareFixtures::make(expires: self::NOW));

        $this->assertNull($this->shares->find(ShareFixtures::ID));
    }

    #[Test]
    public function forgetsEveryLinkToARecord(): void
    {
        $this->store->add(ShareFixtures::make(id: 'one'));
        $this->store->add(ShareFixtures::make(id: 'two'));
        $this->store->add(ShareFixtures::make(
            id: 'other',
            keys: ['resource_id' => '102'],
        ));

        $this->assertSame(
            [2, ['other']],
            [$this->shares->forgetRecord('site.page.home', ['resource_id' => '101']), array_keys($this->store->shares)],
        );
    }

    #[Test]
    public function givesALiveLinkANewCode(): void
    {
        $this->store->add(ShareFixtures::make(code: 'OLD000'));

        $this->assertSame(
            ['K7M4QX', 'K7M4QX'],
            [$this->shares->newCode(ShareFixtures::ID)?->code, $this->store->shares[ShareFixtures::ID]->code],
        );
    }

    #[Test]
    public function givesNoNewCodeToALinkThatHasEnded(): void
    {
        $this->store->add(ShareFixtures::make(
            code: 'OLD000',
            expires: self::NOW,
        ));

        $this->assertSame(
            [null, 'OLD000'],
            [$this->shares->newCode(ShareFixtures::ID), $this->store->shares[ShareFixtures::ID]->code],
        );
    }

    #[Test]
    public function givesNoNewCodeToALinkThatIsGone(): void
    {
        $this->assertNull($this->shares->newCode(ShareFixtures::ID));
    }

    #[Test]
    public function givesNoNewCodeWhenTheLinkGoesAsItIsChanged(): void
    {
        $store = $this->createStub(ShareStoreInterface::class);
        $store->method('find')->willReturn(ShareFixtures::make());
        $store->method('replaceCode')->willReturn(false);

        $this->assertNull((new Shares($store, $this->clock))->newCode(ShareFixtures::ID));
    }

    #[Test]
    public function keepsANoteOnlyWhenItSaysSomething(): void
    {
        $this->assertSame(
            [null, null, 'Jane, Acme'],
            [
                $this->create(note: null)->note,
                $this->create(note: '   ')->note,
                $this->create(note: '  Jane, Acme ')->note,
            ],
        );
    }

    #[Test]
    public function letsALinkLastASecond(): void
    {
        $this->assertSame(self::NOW + 1, $this->create(seconds: 1)->expires->getTimestamp());
    }

    #[Test]
    public function letsALinkLastNinetyDays(): void
    {
        $share = $this->create(seconds: Shares::LONGEST);

        $this->assertSame(self::NOW + Shares::LONGEST, $share->expires->getTimestamp());
    }

    #[Test]
    public function letsANoteRunTo255Characters(): void
    {
        $this->assertSame(
            str_repeat('é', times: 255),
            $this->create(note: str_repeat('é', times: 255))->note,
        );
    }

    #[Test]
    public function listsARecordsLiveLinksEndingSoonestFirst(): void
    {
        $this->store->add(ShareFixtures::make(
            id: 'later',
            expires: self::NOW + 200,
        ));
        $this->store->add(ShareFixtures::make(
            id: 'ended',
            expires: self::NOW,
        ));
        $this->store->add(ShareFixtures::make(
            id: 'sooner',
            expires: self::NOW + 100,
        ));
        $this->store->add(ShareFixtures::make(
            id: 'other',
            keys: ['resource_id' => '102'],
        ));

        $this->assertSame(
            ['sooner', 'later'],
            array_map(
                static fn(Share $share): string => $share->id,
                $this->shares->forRecord('site.page.home', ['resource_id' => '101']),
            ),
        );
    }

    #[Test]
    public function makesALinkToASavedRecordToTheSecond(): void
    {
        $ends  = new DateTimeImmutable('@' . (self::NOW + 3600) . '.75');
        $share = $this->shares->create('site.page.home', ['resource_id' => '101'], $ends, 'Jane', '12');

        $this->assertEquals(
            [
                new Share(
                    ShareFixtures::ID,
                    'K7M4QX',
                    'site.page.home',
                    ['resource_id' => '101'],
                    'Jane',
                    '12',
                    self::at(self::NOW),
                    self::at(self::NOW + 3600),
                ),
                '000000',
            ],
            [$share, $this->store->shares[ShareFixtures::ID]->expires->format('u')],
        );
    }

    #[Test]
    public function prunesTheLinksThatHaveEnded(): void
    {
        $this->store->add(ShareFixtures::make(
            id: 'ended',
            expires: self::NOW,
        ));
        $this->store->add(ShareFixtures::make(
            id: 'live',
            expires: self::NOW + 1,
        ));

        $this->assertSame([1, ['live']], [$this->shares->prune(), array_keys($this->store->shares)]);
    }

    /**
     * @param array<string, string> $keys
     */
    #[Test]
    #[DataProvider('linksRefused')]
    public function refusesALinkThatCannotBeMade(array $keys, int $seconds, ?string $note, string $reason): void
    {
        $this->expectExceptionObject(new InvalidArgumentException($reason));

        $this->shares->create('site.page.home', $keys, self::at(self::NOW + $seconds), $note, '12');
    }

    #[Test]
    public function revokesALink(): void
    {
        $this->store->add(ShareFixtures::make());

        $this->assertSame(
            [true, false, []],
            [$this->shares->revoke(ShareFixtures::ID), $this->shares->revoke(ShareFixtures::ID), $this->store->shares],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->store  = new InMemoryShareStore();
        $this->clock  = new FrozenClock(self::NOW);
        $codes        = ['K7M4QX', 'PQRS23', 'WXYZ89'];
        $this->shares = new Shares(
            $this->store,
            $this->clock,
            static fn(): string => ShareFixtures::ID,
            static function () use (&$codes): string {
                return array_shift($codes) ?? 'ZZZZZZ';
            },
        );
    }

    private function create(?string $note = null, int $seconds = 3600): Share
    {
        return $this->shares->create(
            'site.page.home',
            ['resource_id' => '101'],
            self::at(self::NOW + $seconds),
            $note,
            '12',
        );
    }
}
