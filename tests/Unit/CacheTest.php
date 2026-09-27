<?php

use Strukt\Cache\Cache;
use Strukt\Cache\Driver\Fs;
use Strukt\Cache\Driver\Memory;
use Strukt\Contract\CacheDriverInterface;
use Strukt\Fs\Fs as FileSystem;

beforeEach(function (): void {
    Memory::reset();
});

test('memory driver stores scalar, null, and nested values fluently', function (): void {
    $driver = new Memory('users');

    expect($driver->put('profile.name', 'Ada'))->toBe($driver)
        ->and($driver->put('profile.roles', ['admin', 'writer']))->toBe($driver)
        ->and($driver->put('nullable', null)->exists('nullable'))->toBeTrue()
        ->and($driver->get('profile.name'))->toBe('Ada')
        ->and($driver->get('profile.roles'))->toBe(['admin', 'writer'])
        ->and($driver->get('missing'))->toBeNull()
        ->and($driver->empty())->toBeFalse();

    $driver->remove('profile.name')->remove('profile.roles')->remove('nullable');

    expect($driver->empty())->toBeTrue();
});

test('cache facade resolves aliases and delegates to a driver', function (): void {
    $cache = Cache::make('settings', 'mem');

    expect($cache)->toBeInstanceOf(Cache::class)
        ->and($cache->driver())->toBeInstanceOf(CacheDriverInterface::class)
        ->and($cache->put('theme', 'dark'))->toBe($cache)
        ->and($cache->get('theme'))->toBe('dark')
        ->and($cache->exists('theme'))->toBeTrue();
});

test('filesystem driver persists JSON and removes empty parent paths', function (): void {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'strukt-cache-' . bin2hex(random_bytes(6));

    try {
        $driver = new Fs('records', $directory);
        $driver->put('users.first', ['name' => 'Ada'])->put('version', 1)->save();

        expect($driver->path())->toBe($directory . DIRECTORY_SEPARATOR . 'records.json')
            ->and((new Fs('records', $directory))->get('users.first'))->toBe(['name' => 'Ada']);

        $reloaded = new Fs('records', $directory);
        $reloaded->remove('users.first')->remove('version')->save();

        expect((new Fs('records', $directory))->empty())->toBeTrue();
    } finally {
        FileSystem::rmdir($directory);
    }
});

test('global cache helper reads and writes dotted keys', function (): void {
    $written = cache('helper.value', 'ready');

    expect($written)->toBeInstanceOf(Cache::class)
        ->and(cache('helper.value'))->toBe('ready')
        ->and(cache('helper'))->toBeInstanceOf(Cache::class)
        ->and(helper('cache'))->toContain('cache', 'memc');
});

test('filesystem cache names reject path traversal', function (): void {
    expect(fn (): Fs => new Fs('../unsafe', sys_get_temp_dir()))
        ->toThrow(InvalidArgumentException::class);
});
