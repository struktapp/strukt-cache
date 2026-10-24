<?php

use Strukt\Cache\Cache;
use Strukt\Cache\Driver\Fs;
use Strukt\Cache\Driver\MemCached;
use Strukt\Cache\Driver\Memory;
use Strukt\Contract\CacheDriverInterface;

if (function_exists('helper')) {
    helper('cache');
}

if (function_exists('alias') && alias('cache') === null) {
    alias('cache', CacheDriverInterface::class);
}

if (function_exists('app')) {
    app()->set('cache.mem', static fn (): string => Memory::class);
    app()->set('cache.memory', static fn (): string => Memory::class);
    app()->set('cache.fs', static fn (): string => Fs::class);
    app()->set('cache.memc', static fn (): string => MemCached::class);
    app()->set('cache.memcached', static fn (): string => MemCached::class);

    $cacheDefaults = static function (): array {
        $driver = Memory::class;
        $namespace = 'default';

        if (function_exists('config')) {
            try {
                $configuredDriver = config('cache.default');
                if (is_string($configuredDriver) && trim($configuredDriver) !== '') {
                    $driver = $configuredDriver;
                }

                $configuredNamespace = config('cache.namespace');
                if (is_string($configuredNamespace) && trim($configuredNamespace) !== '') {
                    $namespace = $configuredNamespace;
                }
            } catch (\Throwable) {
                // Configuration is optional; keep the built-in defaults.
            }
        }

        return [$namespace, $driver];
    };

    app()->set(
        CacheDriverInterface::class,
        static fn (): CacheDriverInterface => Cache::make(...$cacheDefaults())->driver(),
    );
    app()->set('cache', static fn (): Cache => Cache::make(...$cacheDefaults()));
}

if (!function_exists('cache') && function_exists('helper_add') && helper_add('cache')) {
    /**
     * Reads, writes, or opens a named cache namespace.
     *
     * A dotted name such as namespace.key reads or writes one key directly.
     * A name without a dot returns a Cache facade. The helper defaults to the
     * in-memory driver to preserve the legacy helper behavior.
     *
     * @param string $filename Namespace, or namespace and key separated by a dot.
     * @param mixed $value Optional value to store; null reads instead.
     * @param string $driver Driver class or alias.
     * @return mixed Cache facade, stored value, or the facade after a write.
     *
     * @throws \InvalidArgumentException When the namespace or driver is invalid.
     * @throws \RuntimeException When a persistent driver cannot initialize.
     */
    function cache(string $filename, mixed $value = null, string $driver = Memory::class): mixed
    {
        $parts = explode('.', trim($filename), 2);
        $cache = new Cache($parts[0], $driver);

        if (!array_key_exists(1, $parts)) {
            return $cache;
        }

        if ($value !== null) {
            return $cache->put($parts[1], $value);
        }

        return $cache->get($parts[1]);
    }
}

if (!function_exists('memc') && function_exists('helper_add') && helper_add('memc')) {
    /**
     * Opens a named Memcached cache namespace.
     *
     * @param string $name Memcached key and namespace.
     * @return MemCached Memcached driver.
     *
     * @throws \RuntimeException When ext-memcached or the server is unavailable.
     */
    function memc(string $name): MemCached
    {
        return new MemCached($name);
    }
}
