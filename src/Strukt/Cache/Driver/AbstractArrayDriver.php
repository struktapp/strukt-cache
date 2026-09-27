<?php

namespace Strukt\Cache\Driver;

use Strukt\Contract\CacheDriverInterface;

/**
 * Provides fast dot-path operations for array-backed cache drivers.
 */
abstract class AbstractArrayDriver implements CacheDriverInterface
{
    /** Namespace or file name associated with this cache. */
    protected string $name;

    /** @var array<int|string, mixed> Values held by the driver. */
    protected array $data = [];

    /**
     * Creates an array-backed driver namespace.
     *
     * @param string $name Namespace or file name.
     *
     * @throws \InvalidArgumentException When the name is empty.
     */
    public function __construct(string $name)
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('A cache name cannot be empty.');
        }

        $this->name = $name;
    }

    /**
     * Checks whether a complete dot-separated path exists.
     *
     * @param string $key Key path.
     * @return bool True when the path exists, including a stored null value.
     */
    public function exists(string $key): bool
    {
        $parts = $this->parts($key);
        $value = $this->data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return false;
            }

            $value = $value[$part];
        }

        return true;
    }

    /**
     * Checks whether no values remain in the namespace.
     *
     * @return bool True when the backing array has no entries.
     */
    public function empty(): bool
    {
        return $this->data === [];
    }

    /**
     * Stores a value at a dot-separated path.
     *
     * @param string $key Key path.
     * @param mixed $value Value to store.
     * @return static This driver.
     */
    public function put(string $key, mixed $value): static
    {
        $parts = $this->parts($key);
        $target =& $this->data;
        $last = array_pop($parts);

        foreach ($parts as $part) {
            if (!isset($target[$part]) || !is_array($target[$part])) {
                $target[$part] = [];
            }

            $target =& $target[$part];
        }

        $target[$last] = $value;

        return $this;
    }

    /**
     * Reads a value at a dot-separated path.
     *
     * @param string $key Key path.
     * @return mixed Stored value, or null when absent.
     */
    public function get(string $key): mixed
    {
        $parts = $this->parts($key);
        $value = $this->data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }

            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Removes a value and empty parent maps from a dot-separated path.
     *
     * @param string $key Key path.
     * @return static This driver.
     */
    public function remove(string $key): static
    {
        $parts = $this->parts($key);
        $removed = null;
        $this->removePath($this->data, $parts, 0, $removed);

        return $this;
    }

    /**
     * Validates and splits a cache key.
     *
     * @param string $key Key to split.
     * @return list<string> Non-empty key segments.
     *
     * @throws \InvalidArgumentException When the key is empty or malformed.
     */
    protected function parts(string $key): array
    {
        $key = trim($key);
        if ($key === '' || str_starts_with($key, '.') || str_ends_with($key, '.') || str_contains($key, '..')) {
            throw new \InvalidArgumentException('Cache keys must contain non-empty path segments.');
        }

        return explode('.', $key);
    }

    /**
     * Recursively removes a path and prunes empty maps.
     *
     * @param array<int|string, mixed> $target Current map by reference.
     * @param list<string> $parts Key segments.
     * @param int $offset Current segment offset.
     * @param mixed $removed Removed value by reference.
     * @return bool True when the path existed.
     */
    private function removePath(array &$target, array $parts, int $offset, mixed &$removed): bool
    {
        $part = $parts[$offset];
        if (!array_key_exists($part, $target)) {
            return false;
        }

        if ($offset === count($parts) - 1) {
            $removed = $target[$part];
            unset($target[$part]);

            return true;
        }

        if (!is_array($target[$part]) || !$this->removePath($target[$part], $parts, $offset + 1, $removed)) {
            return false;
        }

        if ($target[$part] === []) {
            unset($target[$part]);
        }

        return true;
    }
}
