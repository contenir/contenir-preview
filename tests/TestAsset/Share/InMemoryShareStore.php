<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\TestAsset\Share;

use Contenir\Preview\Share\Share;
use Contenir\Preview\Share\ShareStoreInterface;
use DateTimeImmutable;
use Override;

use function array_filter;
use function array_key_exists;
use function array_values;
use function count;
use function ksort;
use function usort;

/**
 * Links in an array, for testing what uses a store without a database.
 */
final class InMemoryShareStore implements ShareStoreInterface
{
    /** @var array<string, Share> */
    public array $shares = [];

    /**
     * @param array<string, string> $keys
     */
    private static function sameRecord(Share $share, string $section, array $keys): bool
    {
        $stored = $share->keys;
        ksort($stored);
        ksort($keys);

        return $share->section === $section && $stored === $keys;
    }

    #[Override]
    public function add(Share $share): void
    {
        $this->shares[$share->id] = $share;
    }

    #[Override]
    public function delete(string $id): bool
    {
        $found = array_key_exists($id, $this->shares);
        unset($this->shares[$id]);

        return $found;
    }

    #[Override]
    public function deleteExpired(DateTimeImmutable $now): int
    {
        $before       = count($this->shares);
        $this->shares = array_filter($this->shares, static fn(Share $share): bool => $share->isLiveAt($now));

        return $before - count($this->shares);
    }

    #[Override]
    public function deleteForRecord(string $section, array $keys): int
    {
        $before       = count($this->shares);
        $this->shares = array_filter(
            $this->shares,
            static fn(Share $share): bool => ! self::sameRecord($share, $section, $keys),
        );

        return $before - count($this->shares);
    }

    #[Override]
    public function find(string $id): ?Share
    {
        return $this->shares[$id] ?? null;
    }

    #[Override]
    public function forRecord(string $section, array $keys): array
    {
        $shares = array_values(array_filter(
            $this->shares,
            static fn(Share $share): bool => self::sameRecord($share, $section, $keys),
        ));
        usort($shares, static fn(Share $one, Share $other): int => $one->expires <=> $other->expires);

        return $shares;
    }

    #[Override]
    public function replaceCode(string $id, string $code): bool
    {
        if (! array_key_exists($id, $this->shares)) {
            return false;
        }

        $this->shares[$id] = $this->shares[$id]->withCode($code);

        return true;
    }
}
