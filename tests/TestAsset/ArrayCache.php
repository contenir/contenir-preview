<?php

declare(strict_types=1);

namespace Contenir\Preview\Tests\TestAsset;

use DateInterval;
use Override;
use Psr\SimpleCache\CacheInterface;

use function array_key_exists;

/**
 * A PSR-16 cache in an array, which records the lifetime each key was set with and can be told to
 * refuse a key as the standard's InvalidArgumentException.
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public array $values = [];

    /** @var array<string, int|DateInterval|null> */
    public array $ttls = [];

    public function __construct(
        private readonly ?string $refuse = null,
    ) {}

    #[Override]
    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    #[Override]
    public function delete(string $key): bool
    {
        unset($this->values[$this->check($key)]);

        return true;
    }

    #[Override]
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$this->check($key)] ?? $default;
    }

    #[Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $found = [];
        foreach ($keys as $key) {
            $found[$key] = $this->get($key, $default);
        }

        return $found;
    }

    #[Override]
    public function has(string $key): bool
    {
        return array_key_exists($this->check($key), $this->values);
    }

    #[Override]
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->values[$this->check($key)] = $value;
        $this->ttls[$key]                 = $ttl;

        return true;
    }

    #[Override]
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    private function check(string $key): string
    {
        if ($key === $this->refuse) {
            throw new RefusedKey('The cache refuses this key.');
        }

        return $key;
    }
}
