<?php

namespace Strukt\Cache\Driver;

use Strukt\Fs\Fs as FileSystem;
use Strukt\Phar;

/**
 * Persists cache values as JSON files.
 */
final class Fs extends AbstractArrayDriver
{
    /** Directory containing cache files. */
    private string $directory;

    /** Absolute cache file path. */
    private string $path;

    /**
     * Opens or creates a JSON cache file.
     *
     * @param string $file File name without the .json suffix.
     * @param string|null $directory Optional cache directory.
     *
     * @throws \InvalidArgumentException When the file name is unsafe.
     * @throws \RuntimeException When the directory or cache file cannot be read.
     * @throws \JsonException When an existing cache file contains invalid JSON.
     */
    public function __construct(string $file, ?string $directory = null)
    {
        parent::__construct($file);

        if (strpbrk($this->name, '/\\\\') !== false || $this->name === '.' || $this->name === '..') {
            throw new \InvalidArgumentException('Filesystem cache names must be simple file names.');
        }

        $this->directory = rtrim($directory ?? Phar::adapt('.cache'), '/\\\\');
        if ($this->directory === '' || !FileSystem::mkdir($this->directory)) {
            throw new \RuntimeException(sprintf('Unable to create cache directory [%s].', $this->directory));
        }

        $this->path = $this->directory . DIRECTORY_SEPARATOR . $this->name . '.json';
        if (!FileSystem::isFile($this->path)) {
            if (!FileSystem::touchWrite($this->path, "[]")) {
                throw new \RuntimeException(sprintf('Unable to create cache file [%s].', $this->path));
            }

            return;
        }

        $contents = FileSystem::cat($this->path);
        if ($contents === false || trim($contents) === '') {
            $this->data = [];
            return;
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException(sprintf('Cache file [%s] must contain a JSON object or array.', $this->path));
        }

        $this->data = $decoded;
    }

    /**
     * Returns the cache file path.
     *
     * @return string Absolute or rooted JSON file path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Atomically persists the current cache data as pretty JSON.
     *
     * @return void
     *
     * @throws \JsonException When a value cannot be encoded.
     * @throws \RuntimeException When the temporary file or replacement cannot be written.
     */
    public function save(): void
    {
        $contents = json_encode(
            $this->data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        $temporary = $this->path . '.' . bin2hex(random_bytes(8)) . '.tmp';

        if (FileSystem::touchWrite($temporary, $contents) === false) {
            throw new \RuntimeException(sprintf('Unable to write temporary cache file [%s].', $temporary));
        }

        if (!@rename($temporary, $this->path)) {
            @unlink($temporary);
            throw new \RuntimeException(sprintf('Unable to replace cache file [%s].', $this->path));
        }
    }
}
