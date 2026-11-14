<?php

namespace Strukt\Cache;

use Strukt\Cache\Driver\Memory;

/**
 * Resolves cache settings from the shared Strukt configuration.
 *
 * The cache package is usable without the configuration helper, so every
 * lookup falls back to the built-in defaults when configuration is absent.
 */
final class Config
{
    /**
     * Returns the configured cache driver.
     *
     * `cache.driver` is preferred; `cache.default` remains supported for
     * projects that used the earlier key.
     *
     * @return string Driver class or alias.
     */
    public static function driver(): string
    {
        $driver = self::value('cache.driver');
        if (!self::filled($driver)) {
            $driver = self::value('cache.default');
        }

        return self::filled($driver) ? trim((string) $driver) : Memory::class;
    }

    /**
     * Returns the configured cache namespace.
     *
     * @return string Cache namespace or file name.
     */
    public static function namespace(): string
    {
        $namespace = self::value('cache.namespace');

        return self::filled($namespace) ? trim((string) $namespace) : 'default';
    }

    /**
     * Reports whether persistent caching is disabled.
     *
     * @return bool True when every cache should use the Memory driver.
     */
    public static function disabled(): bool
    {
        return in_array(self::value('cache.disable'), [true, 1, '1', 'true'], true);
    }

    /**
     * Reads one configuration value when the config helper is available.
     *
     * @param string $key Dotted configuration key.
     * @return mixed Configured value, or null when unavailable.
     */
    private static function value(string $key): mixed
    {
        if (!function_exists('config')) {
            return null;
        }

        try {
            return config($key);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Reports whether a configured value is a non-empty string or scalar.
     *
     * @param mixed $value Value to inspect.
     * @return bool True when the value can be used.
     */
    private static function filled(mixed $value): bool
    {
        return (is_string($value) || is_int($value) || is_float($value))
            && trim((string) $value) !== '';
    }
}
