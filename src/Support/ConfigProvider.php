<?php

namespace Iquesters\Foundation\Support;

use InvalidArgumentException;
use Iquesters\Foundation\Enums\Module;

class ConfigProvider
{
    protected static array $registry = [];

    /**
     * Register a config class for a module.
     */
    public static function register(string $module, string $class): void
    {
        static::$registry[$module] = $class;
    }

    /**
     * Resolve the BaseConfig for a module.
     */
    public static function from(string $module): BaseConfig
    {
        if (!isset(static::$registry[$module])) {
            throw new InvalidArgumentException("No config registered for module: {$module}");
        }

        $class = static::$registry[$module];
        return new $class();
    }
}