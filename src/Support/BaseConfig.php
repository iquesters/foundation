<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseConfig
{
    protected static ?array $dbConfigs = null;
    protected static ?int $moduleId = null;
    protected string $prefix = '';
    protected string $moduleKey = ''; // e.g., 'user_mgmt'
    protected array $defaults = [];

    public function get(string $key, $default = null)
    {
        $key = strtoupper($key);
        
        Log::debug("🔍 [BaseConfig] Fetching config key: {$key} for module: {$this->moduleKey}");

        // 1️⃣ Try Database (scoped to module)
        $dbValue = $this->fromDatabase($key);
        if (!is_null($dbValue)) {
            $logValue = is_array($dbValue) ? json_encode($dbValue) : $dbValue;
            Log::debug("✅ [BaseConfig] Found in DB: {$key} = {$logValue}");
            return $this->parseValue($dbValue);
        }

        // 2️⃣ Try Environment (with prefix)
        $fullKey = $this->prefix . $key;
        $envValue = env($fullKey);
        if (!is_null($envValue)) {
            Log::debug("✅ [BaseConfig] Found in ENV: {$fullKey} = {$envValue}");
            return $this->parseValue($envValue);
        }

        // 3️⃣ Default
        $finalValue = $default ?? $this->getNestedDefault($key);
        
        $logValue = is_array($finalValue) ? json_encode($finalValue) : $finalValue;
        Log::debug("⚙️ [BaseConfig] Using default for {$key}: {$logValue}");

        return $finalValue;
    }

    public function all(): array
    {
        Log::debug("📦 [BaseConfig] Fetching all config values");
        $result = [];
        
        foreach ($this->defaults as $key => $default) {
            $result[$key] = $this->get($key, $default);
        }
        
        return $result;
    }

    protected function fromDatabase(string $key)
    {
        if (static::$dbConfigs === null || static::$moduleId === null) {
            $this->loadDatabaseConfig();
        }

        if (empty(static::$dbConfigs)) {
            return null;
        }

        // Direct lookup first
        $dbKey = strtoupper($key);
        if (isset(static::$dbConfigs[$dbKey])) {
            return static::$dbConfigs[$dbKey];
        }

        // Try with tilde notation if it contains underscores (for nested lookups)
        // e.g., RECAPTCHA_ENABLED -> RECAPTCHA~ENABLED
        if (str_contains($key, '_')) {
            // Check if this might be a nested key query
            $parts = explode('_', $key);
            if (count($parts) >= 2) {
                // Try to find it as a nested key
                $tildeKey = implode('~', $parts);
                if (isset(static::$dbConfigs[$tildeKey])) {
                    return static::$dbConfigs[$tildeKey];
                }
            }
        }
        
        return null;
    }

    protected function loadDatabaseConfig(): void
    {
        if (empty($this->moduleKey)) {
            Log::error("❌ [BaseConfig] moduleKey is not set in " . get_class($this));
            static::$dbConfigs = [];
            static::$moduleId = null;
            return;
        }

        Log::debug("🗄️ [BaseConfig] Loading config from DB for module: {$this->moduleKey}");

        $cacheKey = "foundation.config.{$this->moduleKey}";
        
        $data = Cache::remember($cacheKey, 3600, function () {
            // First, find the config root
            $configRoot = DB::table('master_data')
                ->where('key', 'config')
                ->where('parent_id', 0)
                ->first();

            if (!$configRoot) {
                Log::warning("⚠️ [BaseConfig] Config root not found");
                return ['configs' => [], 'module_id' => null];
            }

            // Find the module entry
            $moduleData = DB::table('master_data')
                ->where('key', $this->moduleKey)
                ->where('parent_id', $configRoot->id)
                ->first();

            if (!$moduleData) {
                Log::warning("⚠️ [BaseConfig] Module '{$this->moduleKey}' not found in master_data");
                return ['configs' => [], 'module_id' => null];
            }

            // Load all meta values for this module
            $rows = DB::table('master_data_metas')
                ->where('ref_parent', $moduleData->id)
                ->where('status', '!=', 'deleted')
                ->select('meta_key', 'meta_value')
                ->get();

            $configs = $rows->mapWithKeys(function ($row) {
                // Keys in DB are stored WITHOUT module prefix (e.g., RECAPTCHA~ENABLED)
                return [strtoupper($row->meta_key) => $row->meta_value];
            })->toArray();

            Log::debug("📊 [BaseConfig] Loaded " . count($configs) . " config keys for module: {$this->moduleKey}");

            return [
                'configs' => $configs,
                'module_id' => $moduleData->id
            ];
        });

        static::$dbConfigs = $data['configs'];
        static::$moduleId = $data['module_id'];
    }



    protected function getNestedDefault(string $key)
    {
        $lowerKey = strtolower($key);
        
        // Check if it exists in defaults directly
        if (isset($this->defaults[$lowerKey])) {
            return $this->defaults[$lowerKey];
        }

        // Handle nested keys (e.g., RECAPTCHA~SITE_KEY -> recaptcha.site_key)
        if (str_contains($key, '~')) {
            $parts = explode('~', $lowerKey);
            $value = $this->defaults;
            
            foreach ($parts as $part) {
                if (is_array($value) && isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    return null;
                }
            }
            
            return $value;
        }

        return null;
    }

    protected function parseValue($value)
    {
        if (is_string($value)) {
            // Handle boolean strings
            if (in_array(strtolower($value), ['true', 'false'])) {
                return strtolower($value) === 'true';
            }
            
            // Handle numeric strings
            if (is_numeric($value)) {
                return str_contains($value, '.') ? (float) $value : (int) $value;
            }
            
            // Handle JSON strings
            if ($this->isJson($value)) {
                return json_decode($value, true);
            }
        }
        
        return $value;
    }

    protected function isJson(string $string): bool
    {
        if (!str_starts_with($string, '{') && !str_starts_with($string, '[')) {
            return false;
        }
        
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Convert nested array to flat database format
     * Example: ['recaptcha' => ['site_key' => 'xxx']] -> ['RECAPTCHA~SITE_KEY' => 'xxx']
     */
    public function toDatabase(array $config): array
    {
        return $this->flattenConfig($config);
    }

    /**
     * Convert flat database format to nested array
     * Example: ['RECAPTCHA~SITE_KEY' => 'xxx'] -> ['recaptcha' => ['site_key' => 'xxx']]
     */
    public function fromDatabaseFormat(array $flatConfig): array
    {
        $nested = [];
        
        foreach ($flatConfig as $key => $value) {
            $parts = explode('~', strtolower($key));
            $current = &$nested;
            
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $current[$part] = $this->parseValue($value);
                } else {
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }
        }
        
        return $nested;
    }

    protected function flattenConfig(array $config, string $prefix = ''): array
    {
        $flat = [];
        
        foreach ($config as $key => $value) {
            $newKey = $prefix ? $prefix . '~' . strtoupper($key) : strtoupper($key);
            
            if (is_array($value) && !$this->isAssocArrayWithValues($value)) {
                $flat = array_merge($flat, $this->flattenConfig($value, $newKey));
            } else {
                $flat[$newKey] = is_array($value) ? json_encode($value) : $value;
            }
        }
        
        return $flat;
    }

    protected function isAssocArrayWithValues(array $arr): bool
    {
        // Check if it's a simple array with scalar values or should be stored as JSON
        if (empty($arr)) {
            return false;
        }
        
        // If it has numeric keys only, it's a list
        if (array_keys($arr) === range(0, count($arr) - 1)) {
            return true;
        }
        
        // If all values are scalar, treat as JSON object
        foreach ($arr as $value) {
            if (is_array($value)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Save configuration to database
     */
    public function save(array $config): bool
    {
        if (!static::tablesExist()) {
            Log::error("❌ [BaseConfig] Required tables do not exist");
            return false;
        }

        try {
            DB::beginTransaction();

            if (static::$moduleId === null) {
                $this->loadDatabaseConfig();
            }

            if (static::$moduleId === null) {
                Log::error("❌ [BaseConfig] Module ID not found for: {$this->moduleKey}");
                return false;
            }

            $flatConfig = $this->toDatabase($config);

            foreach ($flatConfig as $key => $value) {
                // Keys are stored WITHOUT the module prefix
                DB::table('master_data_metas')->updateOrInsert(
                    [
                        'ref_parent' => static::$moduleId,
                        'meta_key' => $key,
                    ],
                    [
                        'meta_value' => $value,
                        'status' => 'active',
                        'updated_at' => now(),
                    ]
                );
            }

            DB::commit();
            
            // Clear cache
            Cache::forget("foundation.config.{$this->moduleKey}");
            static::$dbConfigs = null;
            
            Log::info("✅ [BaseConfig] Configuration saved for module: {$this->moduleKey}");
            
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("❌ [BaseConfig] Error saving config: " . $e->getMessage());
            return false;
        }
    }

    public static function tablesExist(): bool
    {
        try {
            $exists = DB::getSchemaBuilder()->hasTable('master_data')
                && DB::getSchemaBuilder()->hasTable('master_data_metas');

            Log::debug("🧱 [BaseConfig] Table check: " . ($exists ? 'exists' : 'missing'));
            return $exists;
        } catch (\Throwable $e) {
            Log::error("❌ [BaseConfig] Error checking tables: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear configuration cache
     */
    public static function clearCache(string $moduleKey = null): void
    {
        if ($moduleKey) {
            Cache::forget("foundation.config.{$moduleKey}");
        } else {
            Cache::flush();
        }
        
        static::$dbConfigs = null;
        static::$moduleId = null;
    }
}