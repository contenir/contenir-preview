<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\Integration\Share\Throttle;

use Contenir\Preview\Container\ClockFactory;
use Contenir\Preview\Container\PreviewOptions;
use Contenir\Preview\Container\ShareThrottleFactory;
use Contenir\Preview\Share\Throttle\FileCounter;
use Contenir\Preview\Tests\TestAsset\ArrayContainer;
use Contenir\Preview\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Tests\TestAsset\Previews;
use Contenir\Preview\Tests\Trait\TempDirectoryTrait;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function glob;

/**
 * Counts kept in real files, which every process on one server shares.
 */
#[Group('integration')]
final class FileCounterTest extends TestCase
{
    use TempDirectoryTrait;

    /**
     * @return array<string, array{string}>
     */
    public static function filesNotACount(): array
    {
        return [
            'empty'                => [''],
            'one number'           => ['3'],
            'three numbers'        => ['3 1792236900 1'],
            'words'                => ['three soon'],
            'a count not a number' => ['three 1792236900'],
            'an end not a number'  => ['3 soon'],
        ];
    }

    #[Test]
    public function countsWrongCodesInTheConfiguredDirectory(): void
    {
        $throttle = (new ShareThrottleFactory())(new ArrayContainer([
            PreviewOptions::class => PreviewOptions::fromAppConfig(Previews::appConfig([
                'replay' => ['directory' => $this->directory],
            ])),
            ClockFactory::SERVICE => new FrozenClock(),
        ]));
        $throttle->fail('link', 'visitor');

        $this->assertCount(2, (array) glob("{$this->directory}/contenir-share-*"));
    }

    #[Test]
    public function keepsACountInAFileOfItsOwn(): void
    {
        $counter = new FileCounter("{$this->directory}/", 'share-');
        $counter->put('key', 3, 1_792_236_900);

        $this->assertSame([[3, 1_792_236_900], ["{$this->directory}/share-key"]], [
            $counter->get('key'),
            glob("{$this->directory}/*"),
        ]);
    }

    #[Test]
    #[DataProvider('filesNotACount')]
    public function readsNoCountFromAnythingElse(string $contents): void
    {
        file_put_contents("{$this->directory}/share-key", $contents);

        $this->assertNull((new FileCounter($this->directory, 'share-'))->get('key'));
    }

    #[Test]
    public function readsNoCountWhereThereIsNone(): void
    {
        $this->assertNull((new FileCounter($this->directory, 'share-'))->get('key'));
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpTempDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownTempDirectory();
    }
}
