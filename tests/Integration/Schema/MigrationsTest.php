<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Integration\Schema;

use Contenir\Preview\Tests\TestAsset\Database\TestDatabase;
use Contenir\Preview\Tests\Trait\TestDatabaseTrait;
use Override;
use PhpDb\Migration\SchemaInspector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_values;
use function sort;

#[Group('integration')]
final class MigrationsTest extends TestCase
{
    use TestDatabaseTrait;

    #[Test]
    public function createsTheShareTableWithItsColumns(): void
    {
        $columns = array_map(
            static fn(array $column): string => (string) $column['name'],
            array_values((new SchemaInspector($this->adapter, TestDatabase::metadata($this->adapter)))->getColumns(
                'preview_share',
            )),
        );
        sort($columns);

        $this->assertSame(
            ['code', 'created', 'created_by', 'expires', 'note', 'preview_share_id', 'record_keys', 'section'],
            $columns,
        );
    }

    #[Test]
    public function runningThemAgainChangesNothing(): void
    {
        $this->assertSame([], TestDatabase::migrations($this->adapter)->runPending());
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpDatabase();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}
