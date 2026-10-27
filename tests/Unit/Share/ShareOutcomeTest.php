<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share;

use Contenir\Preview\Share\ShareOutcome;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ShareOutcomeTest extends TestCase
{
    /**
     * @return array<string, array{ShareOutcome, int}>
     */
    public static function statuses(): array
    {
        return [
            'shown'        => [ShareOutcome::Show, 200],
            'asked'        => [ShareOutcome::AskForCode, 200],
            'a wrong code' => [ShareOutcome::WrongCode, 401],
            'admitted'     => [ShareOutcome::Admitted, 303],
        ];
    }

    #[Test]
    #[DataProvider('statuses')]
    public function answersEachOutcomeWithItsStatus(ShareOutcome $outcome, int $status): void
    {
        $this->assertSame($status, $outcome->status());
    }
}
