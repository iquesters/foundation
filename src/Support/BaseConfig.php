<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

abstract class BaseConfig
{
    protected static ?array $dbConfigs = null;
    protected string $prefix = '';
    protected array $defaults = [];

    public function get(string $key, $default = null)
    {
        $key = strtoupper($key);
        $fullKey = $this->prefix . $key;

        // 1️⃣ Database
        $dbValue = $this->fromDatabase($fullKey);
        if (!is_null($dbValue)) {
            return $dbValue;
        }

        // 2️⃣ Environment
        $envValue = env($fullKey);
        if (!is_null($envValue)) {
            return $envValue;
        }

        // 3️⃣ Default
        return $default ?? ($this->defaults[strtolower($key)] ?? null);
    }

    public function all(): array
    {
        $result = [];
        foreach ($this->defaults as $key => $default) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    protected function fromDatabase(string $key)
    {
        if (static::$dbConfigs === null) {
            static::$dbConfigs = Cache::remember('foundation.config.db', 3600, function () {
                if (!self::tableExists()) return [];
                return DB::table('configurations')->pluck('value', 'key')->toArray();
            });
        }
        return static::$dbConfigs[$key] ?? null;
    }

    protected static function tableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('configurations');
        } catch (\Throwable $e) {
            return false;
        }
    }
}