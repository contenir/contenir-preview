<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit;

use Contenir\Preview\PreviewRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The record a preview shows, and how a site recognises it among the records it loads.
 */
#[Group('unit')]
final class PreviewRecordTest extends TestCase
{
    /**
     * @return array<string, array{string, array<string, scalar|null>, bool}>
     */
    public static function loadedRecordProvider(): array
    {
        return [
            'the same key as strings'  => ['site.page.home', ['resource_id' => '101', 'locale' => 'en'], true],
            'the same key as integers' => ['site.page.home', ['locale' => 'en', 'resource_id' => 101], true],
            'another section'          => ['site.page.landing', ['resource_id' => '101', 'locale' => 'en'], false],
            'another record'           => ['site.page.home', ['resource_id' => '102', 'locale' => 'en'], false],
            'part of the key'          => ['site.page.home', ['resource_id' => '101'], false],
            'other columns of the key' => ['site.page.home', ['resource_id' => '101', 'lang' => 'en'], false],
        ];
    }

    #[Test]
    public function givesAFieldsValueOrNothing(): void
    {
        $record = new PreviewRecord('site.page.home', ['resource_id' => '101'], ['title' => 'Spa', 'tags' => ['a']]);

        $this->assertSame(['Spa', ['a'], null], [
            $record->value('title'),
            $record->value('tags'),
            $record->value('missing'),
        ]);
    }

    #[Test]
    public function isNewWithoutAPrimaryKey(): void
    {
        $this->assertSame(
            [true, false],
            [
                (new PreviewRecord('site.page.home', [], []))->isNew(),
                (new PreviewRecord('site.page.home', ['resource_id' => '1'], []))->isNew(),
            ],
        );
    }

    #[Test]
    public function recognisesNoLoadedRecordAsOneNotSavedYet(): void
    {
        $this->assertFalse((new PreviewRecord('site.page.home', [], []))->is('site.page.home', []));
    }

    /**
     * @param array<string, scalar|null> $keys
     */
    #[DataProvider('loadedRecordProvider')]
    #[Test]
    public function recognisesTheLoadedRecordItIs(string $section, array $keys, bool $is): void
    {
        $record = new PreviewRecord('site.page.home', ['resource_id' => '101', 'locale' => 'en'], []);

        $this->assertSame($is, $record->is($section, $keys));
    }
}
