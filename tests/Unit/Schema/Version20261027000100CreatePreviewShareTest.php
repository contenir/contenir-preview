<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Schema;

use Contenir\Preview\Schema\Version20261027000100CreatePreviewShare;
use Contenir\Preview\Tests\Trait\AdapterPlatformStubTrait;
use PhpDb\Migration\SchemaInspector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

#[Group('unit')]
final class Version20261027000100CreatePreviewShareTest extends TestCase
{
    use AdapterPlatformStubTrait;

    #[Test]
    public function createsTheTableOnMysql(): void
    {
        $this->assertSame(
            [
                'CREATE TABLE `preview_share` ('
                    . ' `preview_share_id` CHAR(43) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
                    . ' `code` CHAR(6) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
                    . ' `section` VARCHAR(255) NOT NULL,'
                    . ' `record_keys` VARCHAR(1024) NOT NULL,'
                    . ' `note` VARCHAR(255) NULL,'
                    . ' `created_by` VARCHAR(64) NOT NULL,'
                    . ' `created` DATETIME NOT NULL,'
                    . ' `expires` DATETIME NOT NULL,'
                    . ' PRIMARY KEY (`preview_share_id`),'
                    . ' KEY `preview_share_record` (`section`(191), `record_keys`(191)),'
                    . ' KEY `preview_share_expires` (`expires`)'
                    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            ],
            $this->preview('MySQL', tableExists: false),
        );
    }

    #[Test]
    public function createsTheTableOnSqlite(): void
    {
        $this->assertSame(
            [
                'CREATE TABLE "preview_share" ('
                    . ' "preview_share_id" CHAR(43) NOT NULL PRIMARY KEY,'
                    . ' "code" CHAR(6) NOT NULL,'
                    . ' "section" VARCHAR(255) NOT NULL,'
                    . ' "record_keys" VARCHAR(1024) NOT NULL,'
                    . ' "note" VARCHAR(255) NULL,'
                    . ' "created_by" VARCHAR(64) NOT NULL,'
                    . ' "created" DATETIME NOT NULL,'
                    . ' "expires" DATETIME NOT NULL'
                    . ')',
                'CREATE INDEX IF NOT EXISTS "preview_share_record" ON "preview_share" ("section", "record_keys")',
                'CREATE INDEX IF NOT EXISTS "preview_share_expires" ON "preview_share" ("expires")',
            ],
            $this->preview('SQLite', tableExists: false),
        );
    }

    #[Test]
    public function describesItself(): void
    {
        $migration = new Version20261027000100CreatePreviewShare();

        $this->assertSame(
            ['20261027000100', 'Create the preview_share table'],
            [$migration->getVersion(), $migration->getDescription()],
        );
    }

    #[Test]
    public function leavesAnExistingMysqlTable(): void
    {
        $this->assertSame([], $this->preview('MySQL', tableExists: true));
    }

    #[Test]
    public function onlyEnsuresTheIndexesOfAnExistingSqliteTable(): void
    {
        $this->assertSame(
            [
                'CREATE INDEX IF NOT EXISTS "preview_share_record" ON "preview_share" ("section", "record_keys")',
                'CREATE INDEX IF NOT EXISTS "preview_share_expires" ON "preview_share" ("expires")',
            ],
            $this->preview('SQLite', tableExists: true),
        );
    }

    #[Test]
    public function refusesAnyOtherDatabase(): void
    {
        $this->expectExceptionObject(new UnexpectedValueException(
            'contenir/contenir-preview supports MySQL and SQLite, not PostgreSQL',
        ));

        $this->preview('PostgreSQL', tableExists: false);
    }

    /**
     * @return array<string>
     */
    private function preview(string $platform, bool $tableExists): array
    {
        $inspector = $this->createStub(SchemaInspector::class);
        $inspector->method('tableExists')->willReturnMap([['preview_share', $tableExists]]);

        return (new Version20261027000100CreatePreviewShare())->preview($this->adapterNamed($platform), $inspector);
    }
}
