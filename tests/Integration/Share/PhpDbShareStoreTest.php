<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Integration\Share;

use Contenir\Preview\Share\PhpDbShareStore;
use Contenir\Preview\Share\Share;
use Contenir\Preview\Share\ShareStoreException;
use Contenir\Preview\Tests\TestAsset\Share\Shares;
use Contenir\Preview\Tests\Trait\TestDatabaseTrait;
use DateTimeImmutable;
use Override;
use PhpDb\Adapter\Adapter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function strtolower;

/**
 * Links kept in, and read back from, a real `preview_share` table.
 */
#[Group('integration')]
#[Group('repository')]
final class PhpDbShareStoreTest extends TestCase
{
    use TestDatabaseTrait;

    private PhpDbShareStore $store;

    /**
     * @param list<Share> $shares
     *
     * @return list<string>
     */
    private static function ids(array $shares): array
    {
        return array_map(static fn(Share $share): string => $share->id, $shares);
    }

    #[Test]
    public function deletesALinkOnce(): void
    {
        $this->store->add(Shares::make());
        $this->store->add(Shares::make(id: Shares::idOf('a')));

        $this->assertSame(
            [true, false, null, Shares::idOf('a')],
            [
                $this->store->delete(Shares::ID),
                $this->store->delete(Shares::ID),
                $this->store->find(Shares::ID),
                $this->store->find(Shares::idOf('a'))?->id,
            ],
        );
    }

    #[Test]
    public function deletesEveryLinkToARecord(): void
    {
        $this->store->add(Shares::make(id: Shares::idOf('a')));
        $this->store->add(Shares::make(id: Shares::idOf('b')));
        $this->store->add(Shares::make(
            id: Shares::idOf('c'),
            keys: ['resource_id' => '102'],
        ));
        $this->store->add(Shares::make(
            id: Shares::idOf('d'),
            section: 'library.projects.article',
        ));

        $this->assertSame(
            [2, [Shares::idOf('c')], [Shares::idOf('d')]],
            [
                $this->store->deleteForRecord('site.page.home', ['resource_id' => '101']),
                self::ids($this->store->forRecord('site.page.home', ['resource_id' => '102'])),
                self::ids($this->store->forRecord('library.projects.article', ['resource_id' => '101'])),
            ],
        );
    }

    #[Test]
    public function deletesTheLinksThatEndedByNow(): void
    {
        $now = Shares::CREATED + 100;
        $this->store->add(Shares::make(
            id: Shares::idOf('a'),
            expires: $now - 1,
        ));
        $this->store->add(Shares::make(
            id: Shares::idOf('b'),
            expires: $now,
        ));
        $this->store->add(Shares::make(
            id: Shares::idOf('c'),
            expires: $now + 1,
        ));

        $this->assertSame(
            [2, [Shares::idOf('c')]],
            [
                $this->store->deleteExpired((new DateTimeImmutable())->setTimestamp($now)),
                self::ids($this->store->forRecord('site.page.home', ['resource_id' => '101'])),
            ],
        );
    }

    #[Test]
    public function findsALinkByItsWholeIdTellingCapitalsApart(): void
    {
        $this->store->add(Shares::make());

        $this->assertSame(
            [Shares::ID, null],
            [$this->store->find(Shares::ID)?->id, $this->store->find(strtolower(Shares::ID))],
        );
    }

    #[Test]
    public function givesALinkANewCode(): void
    {
        $this->store->add(Shares::make());

        $this->assertSame(
            [true, 'PQRS23', false],
            [
                $this->store->replaceCode(Shares::ID, 'PQRS23'),
                $this->store->find(Shares::ID)?->code,
                $this->store->replaceCode(Shares::idOf('z'), 'PQRS23'),
            ],
        );
    }

    #[Test]
    public function keepsALinkAsItWasMade(): void
    {
        $this->store->add(Shares::make());

        $this->assertEquals(Shares::make(), $this->store->find(Shares::ID));
    }

    #[Test]
    public function keepsALinkWithoutANote(): void
    {
        $this->store->add(Shares::make(note: null));

        $this->assertNull($this->store->find(Shares::ID)?->note);
    }

    #[Test]
    public function listsARecordsLinksEndingSoonestFirstWhicheverOrderItsKeysCameIn(): void
    {
        $keys = ['resource_id' => '101', 'locale' => 'en'];
        $this->store->add(Shares::make(
            id: Shares::idOf('a'),
            keys: $keys,
            expires: Shares::CREATED + 300,
        ));
        $this->store->add(Shares::make(
            id: Shares::idOf('b'),
            keys: $keys,
            expires: Shares::CREATED + 100,
        ));
        $this->store->add(Shares::make(
            id: Shares::idOf('c'),
            section: 'library.projects.article',
            keys: $keys,
        ));

        $this->assertSame(
            [Shares::idOf('b'), Shares::idOf('a')],
            self::ids($this->store->forRecord('site.page.home', ['locale' => 'en', 'resource_id' => '101'])),
        );
    }

    #[Test]
    public function refusesARowItCannotRead(): void
    {
        $this->adapter->query(
            'INSERT INTO preview_share (preview_share_id, code, section, record_keys, note, created_by, created, expires)'
                . " VALUES ('"
                . Shares::ID
                . "', 'K7M4QX', 'site.page.home', 'not json', NULL, '12',"
                . " '2026-10-17 11:20:00', '2026-10-24 11:20:00')",
            Adapter::QUERY_MODE_EXECUTE,
        );

        $this->expectExceptionObject(ShareStoreException::unreadable('record_keys'));

        $this->store->find(Shares::ID);
    }

    #[Test]
    public function saysWhenTheDatabaseFails(): void
    {
        $this->adapter->query('DROP TABLE preview_share', Adapter::QUERY_MODE_EXECUTE);

        $this->expectException(ShareStoreException::class);
        $this->expectExceptionMessageMatches('/^The preview links could not be read or written: /');

        $this->store->find(Shares::ID);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
        $this->store = new PhpDbShareStore($this->adapter);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}
