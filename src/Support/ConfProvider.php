<?php

namespace Iquesters\Foundation\Support;

use InvalidArgumentException;
use Illuminate\Support\Facades\Log;
use Iquesters\Foundation\Enums\Module;

class ConfProvider
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
    public static function from(string $module): BaseConf
    {
        if (!isset(static::$registry[$module])) {
            throw new InvalidArgumentException("No config registered for module: {$module}");
        }

        $class = static::$registry[$module];
        Log::info("Resolving config for module: {$module} ({$class})");
        return new $class();
    }
}