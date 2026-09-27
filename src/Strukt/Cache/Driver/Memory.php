<?php

namespace Strukt\Cache\Driver;

/**
 * Stores cache values in process-local memory.
 */
final class Memory extends AbstractArrayDriver
{
    /** @var array<string, array<int|string, mixed>> Shared namespaces. */
    private static array $stores = [];

    /**
     * Opens or creates a named memory namespace.
     *
     * @param string $name Namespace name.
     */
    public function __construct(string $name)
    {
        parent::__construct($name);
        self::$stores[$this->name] ??= [];
        $this->data =& self::$stores[$this->name];
    }

    /**
     * Clears every process-local memory namespace.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$stores = [];
    }

    /**
     * Saves the in-memory namespace.
     *
     * @return void
     */
    public function save(): void
    {
    }
}
