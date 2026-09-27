<?php

namespace Strukt\Cache\Driver;

/**
 * Stores one cache namespace in a Memcached server.
 */
final class MemCached extends AbstractArrayDriver
{
    /** @var object Memcached extension instance. */
    private object $memcached;

    /** Item lifetime in seconds; zero means no expiration. */
    private int $ttl;

    /** Whether the last save succeeded. */
    private bool $success = false;

    /**
     * Connects to Memcached and loads a namespace.
     *
     * @param string $name Memcached key and namespace name.
     * @param array<string, mixed> $options Connection options: host, port, ttl,
     * username, and password.
     *
     * @throws \RuntimeException When ext-memcached is unavailable or the
     * server namespace cannot be read.
     */
    public function __construct(string $name, array $options = [])
    {
        parent::__construct($name);

        if (!class_exists(\Memcached::class)) {
            throw new \RuntimeException('The ext-memcached extension is required for the MemCached driver.');
        }

        $host = (string) ($options['host'] ?? self::environment('memcached_host', '127.0.0.1'));
        $port = (int) ($options['port'] ?? self::environment('memcached_port', 11211));
        $this->ttl = max(0, (int) ($options['ttl'] ?? self::environment('memcached_ttl', 0)));
        $username = (string) ($options['username'] ?? self::environment('memcached_username', ''));
        $password = (string) ($options['password'] ?? self::environment('memcached_password', ''));

        $this->memcached = new \Memcached();
        if ($username !== '' && $password !== '') {
            $this->memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $this->memcached->setSaslAuthData($username, $password);
        }

        if (!$this->memcached->addServer($host, $port)) {
            throw new \RuntimeException(sprintf('Unable to add Memcached server %s:%d.', $host, $port));
        }

        $stored = $this->memcached->get($this->name);
        $resultCode = $this->memcached->getResultCode();
        if ($stored === false && $resultCode !== \Memcached::RES_NOTFOUND) {
            throw new \RuntimeException($this->memcached->getResultMessage());
        }

        if (is_array($stored)) {
            $this->data = $stored;
        }
    }

    /**
     * Persists the namespace in Memcached.
     *
     * @return void
     *
     * @throws \RuntimeException When Memcached rejects the value.
     */
    public function save(): void
    {
        $this->success = $this->memcached->set($this->name, $this->data, $this->ttl);
        if (!$this->success) {
            throw new \RuntimeException($this->memcached->getResultMessage());
        }
    }

    /**
     * Reports whether the last save succeeded.
     *
     * @return bool True when the last save was accepted.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Reads an environment option without requiring an environment file.
     *
     * @param string $key Environment key.
     * @param string|int $fallback Fallback value.
     * @return string|int|bool Environment value or fallback.
     */
    private static function environment(string $key, string|int $fallback): string|int|bool
    {
        if (function_exists('env')) {
            try {
                return env($key);
            } catch (\Throwable) {
                // Fall through to the process environment and fallback.
            }
        }

        $value = getenv($key);

        return $value === false ? $fallback : $value;
    }
}
