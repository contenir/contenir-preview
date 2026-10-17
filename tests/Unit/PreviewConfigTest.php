<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Unit;

use Contenir\Preview\PreviewConfig;
use Contenir\Preview\Tests\TestAsset\Tokens;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * The settings the CMS and a site share (§2).
 */
#[Group('unit')]
final class PreviewConfigTest extends TestCase
{
    #[Test]
    public function acceptsASecretOfExactly32Bytes(): void
    {
        $this->assertSame(
            str_repeat('a', times: 32),
            (new PreviewConfig(
                'https://admin.test',
                'https://www.test',
                ['k' => str_repeat('a', times: 32)],
                'k',
            ))->secret(
                'k',
            ),
        );
    }

    #[Test]
    public function findsASecretByItsKeyId(): void
    {
        $config = new PreviewConfig(
            'https://admin.test',
            'https://www.test',
            ['old' => Tokens::SHARED, 'new' => Tokens::SHARED . '2'],
            'new',
        );

        $this->assertSame([Tokens::SHARED . '2', null], [$config->secret('new'), $config->secret('gone')]);
    }

    #[Test]
    public function postsToTheSitesFixedPreviewPath(): void
    {
        $paths = [
            (new PreviewConfig('https://admin.test', 'https://www.test', ['k' => Tokens::SHARED], 'k'))->endpoint(),
            (new PreviewConfig('https://admin.test', 'https://www.test/', ['k' => Tokens::SHARED], 'k'))->endpoint(),
        ];

        $this->assertSame(['https://www.test/api/resource/preview', 'https://www.test/api/resource/preview'], $paths);
    }

    #[Test]
    public function refusesASecretShorterThan32Bytes(): void
    {
        $this->expectExceptionObject(
            new InvalidArgumentException('The secret for the key id "old" is shorter than 32 bytes.'),
        );

        new PreviewConfig(
            'https://admin.test',
            'https://www.test',
            ['old' => str_repeat('a', times: 31), 'new' => Tokens::SHARED],
            'new',
        );
    }

    #[Test]
    public function refusesToSignWithAKeyItDoesNotHold(): void
    {
        $this->expectExceptionObject(new InvalidArgumentException('No secret is configured for the key id "new".'));

        new PreviewConfig('https://admin.test', 'https://www.test', ['old' => Tokens::SHARED], 'new');
    }
}
