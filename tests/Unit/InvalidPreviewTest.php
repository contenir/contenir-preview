<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit;

use Contenir\Preview\InvalidPreview;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Each refusal carries the HTTP status the protocol gives it (§5).
 */
#[Group('unit')]
final class InvalidPreviewTest extends TestCase
{
    /**
     * @return array<string, array{InvalidPreview, int, string}>
     */
    public static function refusalProvider(): array
    {
        return [
            'malformed'       => [InvalidPreview::malformed('Bad.'), 400, 'Bad.'],
            'unauthenticated' => [InvalidPreview::unauthenticated('Unsigned.'), 401, 'Unsigned.'],
            'not previewable' => [InvalidPreview::notPreviewable('No page.'), 404, 'No page.'],
            'fetched'         => [InvalidPreview::wrongMethod(), 405, 'A preview is posted, not fetched.'],
            'replayed'        => [
                InvalidPreview::replayed(),
                409,
                'This preview has already been shown. Refresh the preview to see it again.',
            ],
        ];
    }

    #[DataProvider('refusalProvider')]
    #[Test]
    public function carriesTheProtocolsStatus(InvalidPreview $refusal, int $status, string $reason): void
    {
        $this->assertSame([$status, $status, $reason], [$refusal->status, $refusal->getCode(), $refusal->getMessage()]);
    }
}
