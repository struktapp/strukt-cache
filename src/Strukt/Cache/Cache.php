<?php

namespace Strukt\Cache;

use Strukt\Cache\Driver\Fs;
use Strukt\Cache\Driver\Memory;
use Strukt\Contract\CacheDriverInterface;

/**
 * Fluent facade around a configured cache driver.
 */
class Cache
{
    /** Active cache driver. */
    private CacheDriverInterface $cache;

    /**
     * Creates a cache namespace.
     *
     * The filesystem driver is the default for direct facade use. Setting
     * cache.disable to true in the Strukt configuration selects Memory
     * regardless of the requested persistent driver.
     *
     * @param string $filename Cache namespace or file name.
     * @param string $driver Driver class or alias: mem, fs, or memc.
     *
     * @throws \InvalidArgumentException When the driver is unknown.
     * @throws \RuntimeException When the selected driver cannot initialize.
     */
    public function __construct(string $filename, string $driver = Fs::class)
    {
        $driver = $this->resolveDriver($driver);
        $this->cache = new $driver($filename);
    }

    /**
     * Creates a cache facade through late static binding.
     *
     * @param string $filename Cache namespace or file name.
     * @param string $driver Driver class or alias.
     * @return static New cache facade.
     */
    public static function make(string $filename, string $driver = Fs::class): static
    {
        return new static($filename, $driver);
    }

    /**
     * Returns the active driver.
     *
     * @return CacheDriverInterface Active driver instance.
     */
    public function driver(): CacheDriverInterface
    {
        return $this->cache;
    }

    /**
     * Checks whether a key exists.
     *
     * @param string $key Dot-separated key.
     * @return bool True when the key exists.
     */
    public function exists(string $key): bool
    {
        return $this->cache->exists($key);
    }

    /**
     * Stores a value.
     *
     * @param string $key Dot-separated key.
     * @param mixed $value Value to store.
     * @return static This facade.
     */
    public function put(string $key, mixed $value): static
    {
        $this->cache->put($key, $value);

        return $this;
    }

    /**
     * Reads a value.
     *
     * @param string $key Dot-separated key.
     * @return mixed Stored value, or null when absent.
     */
    public function get(string $key): mixed
    {
        return $this->cache->get($key);
    }

    /**
     * Removes a value.
     *
     * @param string $key Dot-separated key.
     * @return static This facade.
     */
    public function remove(string $key): static
    {
        $this->cache->remove($key);

        return $this;
    }

    /**
     * Checks whether the cache namespace is empty.
     *
     * @return bool True when no values are stored.
     */
    public function empty(): bool
    {
        return $this->cache->empty();
    }

    /**
     * Persists pending changes.
     *
     * @return void
     */
    public function save(): void
    {
        $this->cache->save();
    }

    /**
     * Resolves a driver class or short driver alias.
     *
     * @param string $driver Driver class or alias.
     * @return class-string<CacheDriverInterface> Resolved driver class.
     *
     * @throws \InvalidArgumentException When the driver is not a cache driver.
     */
    private function resolveDriver(string $driver): string
    {
        $driver = trim($driver);
        $aliases = [
            'mem' => Memory::class,
            'memory' => Memory::class,
            'fs' => Fs::class,
            'memc' => Driver\MemCached::class,
            'memcached' => Driver\MemCached::class,
        ];
        $resolved = $aliases[$driver] ?? $driver;

        if (!class_exists($resolved) && function_exists('app')) {
            $registered = app('cache.' . $driver);
            $resolved = is_string($registered) ? $registered : $resolved;
        }

        if (!class_exists($resolved) || !is_a($resolved, CacheDriverInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown cache driver [%s].', $driver));
        }

        if (function_exists('config')) {
            try {
                $disabled = config('cache.disable');
                if ($disabled === true || $disabled === 'true' || $disabled === 1 || $disabled === '1') {
                    return Memory::class;
                }
            } catch (\Throwable) {
                // Configuration is optional; retain the requested driver.
            }
        }

        return $resolved;
    }
}
