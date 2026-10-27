<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share;

use Contenir\Preview\Share\ShareRow;
use Contenir\Preview\Share\ShareStoreException;
use Contenir\Preview\Tests\TestAsset\Share\Shares;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A link as a row and back.
 */
#[Group('unit')]
final class ShareRowTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function rowsUnreadable(): array
    {
        $row = self::row();

        return [
            'no id'               => [[...$row, 'preview_share_id' => null], 'preview_share_id'],
            'keys not JSON'       => [[...$row, 'record_keys' => '{'], 'record_keys'],
            'keys not text'       => [[...$row, 'record_keys' => 5], 'record_keys'],
            'keys a list'         => [[...$row, 'record_keys' => '["101"]'], 'record_keys'],
            'keys not a map'      => [[...$row, 'record_keys' => '"101"'], 'record_keys'],
            'a key not text'      => [[...$row, 'record_keys' => '{"resource_id":101}'], 'record_keys'],
            'a time not a time'   => [[...$row, 'expires' => 'soon'], 'expires'],
            'a creator not given' => [[...$row, 'created_by' => false], 'created_by'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(): array
    {
        return [
            'preview_share_id' => Shares::ID,
            'code'             => Shares::CODE,
            'section'          => 'site.page.home',
            'record_keys'      => '{"resource_id":"101"}',
            'note'             => 'Jane, Acme',
            'created_by'       => '12',
            'created'          => '2026-10-17 11:20:00',
            'expires'          => '2026-10-24 11:20:00',
        ];
    }

    #[Test]
    public function keepsAKeysColumnsInOrderSoOneRecordAlwaysReadsTheSame(): void
    {
        $this->assertSame(
            ['{"a":"1","b":"/2"}', '{"a":"1","b":"/2"}', '{}'],
            [ShareRow::keys(['b' => '/2', 'a' => '1']), ShareRow::keys(['a' => '1', 'b' => '/2']), ShareRow::keys([])],
        );
    }

    #[Test]
    public function readsAKeyOfSeveralColumns(): void
    {
        $share = ShareRow::share([...self::row(), 'record_keys' => '{"locale":"en","resource_id":"101"}']);

        $this->assertSame(['locale' => 'en', 'resource_id' => '101'], $share->keys);
    }

    #[Test]
    public function readsALinkBackFromItsRow(): void
    {
        $this->assertEquals(Shares::make(), ShareRow::share(self::row()));
    }

    #[Test]
    public function readsANumberedCreatorAndAnEmptyNote(): void
    {
        $share = ShareRow::share([...self::row(), 'created_by' => 12, 'note' => '']);

        $this->assertSame(['12', null], [$share->createdBy, $share->note]);
    }

    #[Test]
    public function refusesAKeyThatIsNotUtf8(): void
    {
        $this->expectException(ShareStoreException::class);
        $this->expectExceptionMessage('The preview links could not be read or written: Malformed UTF-8');

        ShareRow::keys(['resource_id' => "\xB1"]);
    }

    /**
     * @param array<string, mixed> $row
     */
    #[Test]
    #[DataProvider('rowsUnreadable')]
    public function refusesARowItCannotRead(array $row, string $column): void
    {
        $this->expectExceptionObject(new ShareStoreException("A preview link's {$column} cannot be read."));

        ShareRow::share($row);
    }

    #[Test]
    public function writesALinkAsARowInUtc(): void
    {
        $this->assertSame(self::row(), ShareRow::values(Shares::make()));
    }
}
