<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share;

use Contenir\Preview\PreviewRecord;
use Contenir\Preview\Tests\TestAsset\Share\Shares;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A link's record and lifetime.
 */
#[Group('unit')]
final class ShareTest extends TestCase
{
    #[Test]
    public function keepsEverythingButTheCodeWhenTheCodeChanges(): void
    {
        $share = Shares::make();

        $this->assertEquals(Shares::make(code: 'PQRS23'), $share->withCode('PQRS23'));
    }

    #[Test]
    public function opensUntilTheMomentItEnds(): void
    {
        $share = Shares::make(expires: 1_792_240_000);

        $this->assertSame(
            [true, false],
            [
                $share->isLiveAt((new DateTimeImmutable())->setTimestamp(1_792_239_999)),
                $share->isLiveAt((new DateTimeImmutable())->setTimestamp(1_792_240_000)),
            ],
        );
    }

    #[Test]
    public function showsItsRecordAsItIsSaved(): void
    {
        $this->assertEquals(
            new PreviewRecord('site.page.home', ['resource_id' => '101'], []),
            Shares::make()->record(),
        );
    }
}
