<?php

namespace Iquesters\Foundation\Support;

use ArrayObject;
use InvalidArgumentException;

class ConfProvider
{
    /**
     * Container key of the per-request instance cache. Bound as a scoped
     * instance, so Laravel drops it after each request / queue job and a
     * long-running worker never keeps stale settings.
     */
    private const INSTANCES = 'foundation.conf.instances';

    protected static array $registry = [];

    /**
     * Register a config class for a module.
     */
    public static function register(string $module, string $class): void
    {
        static::$registry[$module] = $class;
        static::forget($module);
    }

    /** Whether $module has a registered (root) settings class. */
    public static function isRegistered(string $module): bool
    {
        return isset(static::$registry[$module]);
    }

    /** @return array<string, class-string<BaseConf>> module => settings class */
    public static function registered(): array
    {
        return static::$registry;
    }

    /**
     * Resolve the BaseConf for a module.
     *
     * Building a conf runs reflection, a table check and a cache read, and
     * views call this many times per page, so the instance is reused for the
     * rest of the request.
     */
    public static function from(string $module): BaseConf
    {
        if (! isset(static::$registry[$module])) {
            throw new InvalidArgumentException("No config registered for module: {$module}");
        }

        $instances = static::instances();

        if ($instances === null) {
            return new (static::$registry[$module])();
        }

        if (! isset($instances[$module])) {
            $instances[$module] = new (static::$registry[$module])();
        }

        return $instances[$module];
    }

    /**
     * Drop the cached instance(s) so the next from() rebuilds them,
     * e.g. after settings were written in the same request.
     */
    public static function forget(?string $module = null): void
    {
        $instances = static::instances();

        if ($instances === null) {
            return;
        }

        if ($module === null) {
            $instances->exchangeArray([]);
        } else {
            unset($instances[$module]);
        }
    }

    private static function instances(): ?ArrayObject
    {
        if (! function_exists('app') || ! app()->bound('config')) {
            return null;
        }

        $app = app();

        if (! $app->bound(self::INSTANCES)) {
            $app->scoped(self::INSTANCES, fn () => new ArrayObject);
        }

        return $app->make(self::INSTANCES);
    }
}
