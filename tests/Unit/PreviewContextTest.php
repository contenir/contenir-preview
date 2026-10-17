<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit;

use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewRecord;
use Contenir\Preview\PreviewRequest;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The preview a site renders for the rest of the request, and the values it lays over the site's
 * own records.
 */
#[Group('unit')]
final class PreviewContextTest extends TestCase
{
    #[Test]
    public function beginsOnePreviewARequest(): void
    {
        $context = new PreviewContext();
        $context->begin($this->request(['resource_id' => '101']));

        $this->expectExceptionObject(new LogicException('A preview has already begun in this request.'));

        $context->begin($this->request(['resource_id' => '101']));
    }

    #[Test]
    public function givesNoUnsavedValuesForASavedRecord(): void
    {
        $context = new PreviewContext();
        $context->begin($this->request(['resource_id' => '101']));

        $this->assertNull($context->unsaved('site.page.home'));
    }

    #[Test]
    public function givesTheValuesOfARecordNotSavedYetToItsSectionOnly(): void
    {
        $context = new PreviewContext();
        $context->begin($this->request([]));

        $this->assertSame(
            [['title' => 'Edited', 'tags' => ['a']], null],
            [$context->unsaved('site.page.home'), $context->unsaved('library.news.articles')],
        );
    }

    #[Test]
    public function holdsThePreviewItBegins(): void
    {
        $context = new PreviewContext();
        $request = $this->request(['resource_id' => '101']);
        $context->begin($request);

        $this->assertSame([true, $request], [$context->isActive(), $context->request()]);
    }

    #[Test]
    public function laysThePreviewsValuesOverThePreviewedRecord(): void
    {
        $context = new PreviewContext();
        $context->begin($this->request(['resource_id' => '101']));

        $this->assertSame(
            ['resource_id' => 101, 'title' => 'Edited', 'created' => '2026-01-01', 'tags' => ['a']],
            $context->overlay(
                'site.page.home',
                ['resource_id' => 101],
                ['resource_id' => 101, 'title' => 'Stored', 'created' => '2026-01-01'],
            ),
        );
    }

    #[Test]
    public function leavesOtherRecordsAsTheyAre(): void
    {
        $context = new PreviewContext();
        $context->begin($this->request(['resource_id' => '101']));

        $this->assertSame(
            ['title' => 'Stored'],
            $context->overlay('site.page.home', ['resource_id' => '102'], ['title' => 'Stored']),
        );
    }

    #[Test]
    public function leavesTheSitesDataAloneOutsideAPreview(): void
    {
        $context = new PreviewContext();

        $this->assertSame(
            [false, null, ['title' => 'Stored'], null],
            [
                $context->isActive(),
                $context->request(),
                $context->overlay('site.page.home', ['resource_id' => '101'], ['title' => 'Stored']),
                $context->unsaved('site.page.home'),
            ],
        );
    }

    /**
     * @param array<string, string> $keys
     */
    private function request(array $keys): PreviewRequest
    {
        return new PreviewRequest(
            new PreviewRecord('site.page.home', $keys, ['title' => 'Edited', 'tags' => ['a']]),
            '12',
            '0123456789abcdef0123456789abcdef',
            new DateTimeImmutable('@1792236000'),
            new DateTimeImmutable('@1792236300'),
        );
    }
}
