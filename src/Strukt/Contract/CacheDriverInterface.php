<?php

namespace Strukt\Contract;

/**
 * Defines the storage operations required by a cache driver.
 */
interface CacheDriverInterface
{
    /**
     * Checks whether a key exists.
     *
     * @param string $key Dot-separated key to inspect.
     * @return bool True when the complete key path exists.
     */
    public function exists(string $key): bool;

    /**
     * Checks whether the driver contains no values.
     *
     * @return bool True when the cache namespace is empty.
     */
    public function empty(): bool;

    /**
     * Stores a value.
     *
     * @param string $key Dot-separated key to write.
     * @param mixed $value Value to store.
     * @return static This driver.
     */
    public function put(string $key, mixed $value): static;

    /**
     * Reads a value.
     *
     * @param string $key Dot-separated key to read.
     * @return mixed Stored value, or null when absent.
     */
    public function get(string $key): mixed;

    /**
     * Removes a value.
     *
     * @param string $key Dot-separated key to remove.
     * @return static This driver.
     */
    public function remove(string $key): static;

    /**
     * Persists pending changes.
     *
     * @return void
     */
    public function save(): void;
}
