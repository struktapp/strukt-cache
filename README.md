# Strukt Cache

[![Latest Stable Version](https://img.shields.io/packagist/v/strukt/cache.svg)](https://packagist.org/packages/strukt/cache)
[![License](https://img.shields.io/packagist/l/strukt/cache.svg)](https://packagist.org/packages/strukt/cache)

Typed cache storage for PHP 8.2 and later. Strukt Cache provides fast
process-local memory storage, JSON filesystem persistence, and an optional
Memcached driver behind one fluent API.

This package is the Strukt 3 rewrite of 'strukt2/cache'. It preserves the
cache and memc helpers, dotted keys, fluent driver methods, and explicit
save() lifecycle while improving validation, filesystem safety, JSON errors,
driver resolution, and documentation.

## Installation

```bash
composer require strukt/cache
```

The package uses:

- strukt/base for the helper registry and environment values;
- strukt/commons for application aliases and configuration;
- strukt/fs for filesystem operations.

## Quick start

The cache helper opens an in-memory namespace by default:

```php
<?php

cache('settings.theme', 'dark');

echo cache('settings.theme'); // dark
```

For an explicit fluent facade:

```php
use Strukt\Cache\Cache;
use Strukt\Cache\Driver\Memory;

$cache = Cache::make('settings', Memory::class);
$cache->put('theme', 'dark');

echo $cache->get('theme');
```

Persistent filesystem caches must be saved:

```php
use Strukt\Cache\Cache;
use Strukt\Cache\Driver\Fs;

$cache = new Cache('settings', Fs::class);
$cache->put('theme', 'dark')->save();
```

## Drivers

| Driver | Class | Persistence | Notes |
| --- | --- | --- | --- |
| Memory | Strukt\Cache\Driver\Memory | Current PHP process | Fast, shared by namespace, and does not write to disk. |
| Filesystem | Strukt\Cache\Driver\Fs | JSON file | Uses atomic replacement and defaults to the .cache directory. |
| Memcached | Strukt\Cache\Driver\MemCached | Memcached server | Requires ext-memcached and an available server. |

All drivers implement Strukt\Contract\CacheDriverInterface.

## Global helpers

The autoloaded src/helpers.php registers the cache helpers with the Strukt
helper registry and registers the cache driver aliases.

| Helper | Parameters | Return type | Description |
| --- | --- | --- | --- |
| cache | string $filename, mixed $value = null, string $driver = Memory::class | mixed | Opens a namespace, reads a dotted key, or writes a dotted key. |
| memc | string $name | MemCached | Opens a Memcached namespace. |

The helpers are defined conditionally so applications can provide their own
functions without a redeclaration error.

### cache helper behavior

With no dot in the name, cache() returns a Cache facade:

```php
$cache = cache('settings');
$cache->put('theme', 'dark');
```

With a dot, the first segment is the namespace and the remainder is the key:

```php
cache('settings.theme', 'dark');
$theme = cache('settings.theme');
```

The helper defaults to the Memory driver to match the Strukt 2 helper. Pass a
driver class or alias as the third argument when persistence is needed:

```php
cache('settings.theme', 'dark', 'fs');

$theme = cache('settings.theme', null, 'fs');
```

The helper treats a null value as a read request. Use the fluent Cache facade
when a null value must be stored explicitly.

## Cache facade

Strukt\Cache\Cache delegates storage to a selected cache driver.

### Constructor

```php
public function __construct(
    string $filename,
    string $driver = Strukt\Cache\Driver\Fs::class,
)
```

Creates a namespace using a driver class or one of these aliases:

- mem or memory for Memory;
- fs for Fs;
- memc or memcached for MemCached.

Direct facade construction defaults to the filesystem driver. If
config('cache.disable') is true, the Memory driver is selected regardless of
the requested persistent driver.

### make

```php
public static function make(
    string $filename,
    string $driver = Strukt\Cache\Driver\Fs::class,
): static
```

Creates a cache facade through late static binding.

### driver

```php
public function driver(): CacheDriverInterface
```

Returns the active driver instance.

### exists

```php
public function exists(string $key): bool
```

Checks whether a complete dotted key path exists. A stored null value still
counts as existing.

### put

```php
public function put(string $key, mixed $value): static
```

Stores a value and returns the facade for fluent chaining. Values must be
serializable when the filesystem or Memcached driver is saved.

### get

```php
public function get(string $key): mixed
```

Returns the value at a dotted key path, or null when the key is absent.

### remove

```php
public function remove(string $key): static
```

Removes a value and returns the facade. Empty parent maps are pruned.

### empty

```php
public function empty(): bool
```

Returns true when the namespace has no stored values.

### save

```php
public function save(): void
```

Persists pending changes through the active driver. Memory save() is a
deliberate no-op.

## Cache driver contract

Strukt\Contract\CacheDriverInterface defines the common driver API:

| Method | Parameters | Return type | Behavior |
| --- | --- | --- | --- |
| exists | string $key | bool | Checks a complete dotted path. |
| empty | none | bool | Checks whether the namespace contains no values. |
| put | string $key, mixed $value | static | Stores a value and returns the driver. |
| get | string $key | mixed | Reads a value or returns null. |
| remove | string $key | static | Removes a value and returns the driver. |
| save | none | void | Persists pending changes. |

Keys must contain non-empty segments. Empty keys, leading or trailing dots,
and consecutive dots throw InvalidArgumentException.

## Memory driver

Strukt\Cache\Driver\Memory stores values in a static process-local map keyed
by namespace name. Different Memory instances with the same name see the same
values during the current PHP process.

```php
use Strukt\Cache\Driver\Memory;

$first = new Memory('runtime');
$first->put('user.id', 42);

$second = new Memory('runtime');
echo $second->get('user.id'); // 42
```

### Constructor

```php
public function __construct(string $name)
```

Opens or creates a named process-local namespace.

### reset

```php
public static function reset(): void
```

Clears all process-local namespaces. This is useful for test isolation and
long-running workers that need an explicit cache reset.

### save

```php
public function save(): void
```

Does nothing because memory values are already live in the current process.

## Filesystem driver

Strukt\Cache\Driver\Fs stores one namespace in a JSON file named
namespace.json. The default directory is the current application directory's
.cache folder, adapted for Phar execution.

```php
use Strukt\Cache\Driver\Fs;

$cache = new Fs('settings');
$cache->put('theme', 'dark')->save();

$reloaded = new Fs('settings');
echo $reloaded->get('theme');
```

### Constructor

```php
public function __construct(
    string $file,
    ?string $directory = null,
)
```

Opens or creates a JSON cache file. The file name must be a simple name
without directory separators; this prevents cache path traversal.

When directory is null, the driver uses the adapted .cache directory. A
custom directory is useful for tests, isolated applications, and workers:

```php
$cache = new Fs('settings', sys_get_temp_dir() . '/my-cache');
```

### path

```php
public function path(): string
```

Returns the JSON file path used by the driver.

### save

```php
public function save(): void
```

Encodes the complete namespace as pretty JSON, writes a unique temporary file
with an exclusive lock, and atomically replaces the destination. It throws
JsonException for values that cannot be encoded and RuntimeException for
filesystem failures.

Existing invalid JSON is rejected during construction with JsonException.

## Memcached driver

Strukt\Cache\Driver\MemCached stores the complete namespace under one
Memcached item. The driver is optional and does not require the PHP extension
unless it is instantiated.

```php
use Strukt\Cache\Driver\MemCached;

$cache = new MemCached('settings', [
    'host' => '127.0.0.1',
    'port' => 11211,
    'ttl' => 3600,
]);

$cache->put('theme', 'dark')->save();
```

### Constructor

```php
public function __construct(
    string $name,
    array $options = [],
)
```

Options are host, port, ttl, username, and password. When omitted, values
are read from the Strukt environment keys memcached_host,
memcached_port, memcached_ttl, memcached_username, and memcached_password.
Defaults are 127.0.0.1, 11211, zero TTL, and no authentication.

The constructor throws RuntimeException when ext-memcached is unavailable, a
server cannot be added, or an existing item cannot be read.

### save

```php
public function save(): void
```

Writes the complete namespace to Memcached with the configured TTL. A rejected
write throws RuntimeException and leaves isSuccess() false.

### isSuccess

```php
public function isSuccess(): bool
```

Reports whether the most recent save was accepted by Memcached.

## Configuration and driver registration

The package registers the cache driver interface and implementations through
the Strukt application registry:

```php
app('cache');       // default registered driver
app('cache.*');     // all registered drivers
app('cache.mem');   // Memory class
app('cache.fs');    // filesystem class
app('cache.memc');  // Memcached class
```

Set cache.disable to true to force the Cache facade to use memory storage:

```php
config('cache.disable', true);

$cache = new Cache('temporary', 'fs');
// Uses Memory while cache.disable is enabled.
```

This setting is intended for tests, local development, and environments where
persistent cache storage should be disabled.

## Persistence lifecycle

Memory changes are immediately visible to other instances that use the same
namespace. Filesystem and Memcached changes remain local to the driver until
save() is called.

Recommended lifecycle:

1. Create a cache namespace.
2. Read or mutate values with exists(), get(), put(), and remove().
3. Call save() for filesystem or Memcached storage.
4. Reopen the namespace to verify persisted values when needed.

## Performance and safety

- Memory storage uses direct arrays and avoids collection wrapper allocation.
- Dotted path traversal is performed in one pass without repeated string
  wrapper creation.
- Filesystem writes use a temporary file and atomic replacement.
- JSON failures are surfaced as exceptions instead of silently creating empty
  caches.
- Filesystem cache names reject path separators.
- Memcached setup is lazy and does not make the package unusable when the PHP
  extension is not installed.

## Development

Install development dependencies and run the Pest suite:

```bash
composer update
composer test
```

Validate package metadata:

```bash
composer validate --no-check-publish
```

The test suite covers memory storage, nested keys, null values, facade driver
resolution, filesystem persistence and reloads, atomic-path setup, helper
registration, and path traversal protection.

## License

Strukt Cache is released under the MIT license.
