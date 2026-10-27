<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit\Share;

use Contenir\Preview\Share\ShareStoreException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('unit')]
final class ShareStoreExceptionTest extends TestCase
{
    #[Test]
    public function saysWhatTheDatabaseSaidAndKeepsIt(): void
    {
        $cause   = new RuntimeException('no such table');
        $failure = ShareStoreException::failed($cause);

        $this->assertSame(
            ['The preview links could not be read or written: no such table', $cause],
            [$failure->getMessage(), $failure->getPrevious()],
        );
    }
}
