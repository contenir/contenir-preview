<?php

declare(strict_types=1);

namespace Contenir\Preview\Schema;

use Override;
use PhpDb\Migration\AbstractMigration;
use UnexpectedValueException;

/**
 * The `preview_share` table: one row per link an editor made to a record's draft (§9.1). The id
 * is compared byte for byte (ascii_bin on MySQL), since base64url tells capitals from small letters.
 *
 * @internal Run by the host's migration runner.
 *
 * @mago-expect analysis:missing-constructor AbstractMigration sets its adapter and inspector in up() and preview().
 */
final class Version20261027000100CreatePreviewShare extends AbstractMigration
{
    #[Override]
    public function getDescription(): string
    {
        return 'Create the preview_share table';
    }

    #[Override]
    public function getVersion(): string
    {
        return '20261027000100';
    }

    /**
     * @throws UnexpectedValueException On a database other than MySQL or SQLite.
     */
    #[Override]
    protected function define(): void
    {
        $exists = $this->inspector->tableExists('preview_share');

        if (Platform::isMysql($this->adapter)) {
            $this->executeSqlIf(
                ! $exists,
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
                'Create table "preview_share"',
                'Table "preview_share" already exists',
            );

            return;
        }

        $this->executeSqlIf(
            ! $exists,
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
            'Create table "preview_share"',
            'Table "preview_share" already exists',
        );
        $this->executeSql(
            'CREATE INDEX IF NOT EXISTS "preview_share_record" ON "preview_share" ("section", "record_keys")',
            'Index "preview_share.preview_share_record"',
        );
        $this->executeSql(
            'CREATE INDEX IF NOT EXISTS "preview_share_expires" ON "preview_share" ("expires")',
            'Index "preview_share.preview_share_expires"',
        );
    }
}
