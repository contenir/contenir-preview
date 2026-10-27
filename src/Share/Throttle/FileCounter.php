<?php

declare(strict_types=1);

namespace Contenir\Preview\Share\Throttle;

use Override;

use function count;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function is_file;
use function is_numeric;

use const LOCK_EX;

/**
 * Counts in files in a directory every process on one server shares, one small file per key. It
 * touches only files that start with its prefix, and leaves a closed window's file to be
 * overwritten by the next count.
 *
 * @api
 */
final readonly class FileCounter implements CounterInterface
{
    public function __construct(
        private string $directory,
        private string $prefix,
    ) {}

    #[Override]
    public function get(string $key): ?array
    {
        $file = $this->file($key);
        if (! is_file($file)) {
            return null;
        }

        $parts = explode(' ', (string) file_get_contents($file));
        if (2 !== count($parts) || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
            return null;
        }

        return [(int) $parts[0], (int) $parts[1]];
    }

    #[Override]
    public function put(string $key, int $count, int $until): void
    {
        file_put_contents($this->file($key), "{$count} {$until}", LOCK_EX);
    }

    private function file(string $key): string
    {
        return "{$this->directory}/{$this->prefix}{$key}";
    }
}
