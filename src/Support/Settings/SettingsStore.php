<?php

namespace Iquesters\Foundation\Support\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Iquesters\Foundation\Support\ConfSecret;

/**
 * Persistence for module settings: one JSON document per module in
 * `module_settings`, holding only the values that differ from the code
 * defaults (the defaults live in each conf class's prepareDefault()).
 *
 * Secret values (keys containing secret/token/password/api_key, see
 * ConfSecret) are stored encrypted ("enc:..."); this class returns them as
 * stored, decryption happens when the conf is hydrated.
 */
final class SettingsStore
{
    public const TABLE = 'module_settings';

    /** Container key for "the settings table exists" (per application instance). */
    private const READY_KEY = 'foundation.settings.table_ready';

    /** Stored overrides for a module ([] when none). Cached until the next write. */
    public static function get(string $module): array
    {
        if (! self::tableReady()) {
            return [];
        }

        return Cache::rememberForever(self::cacheKey($module), function () use ($module) {
            $json = DB::table(self::TABLE)->where('module', $module)->value('settings');
            $decoded = is_string($json) ? json_decode($json, true) : null;

            return is_array($decoded) ? $decoded : [];
        });
    }

    /** Replace the stored overrides for a module. Secrets are encrypted before saving. */
    public static function put(string $module, array $overrides, ?int $userId = null): void
    {
        $payload = json_encode(self::encryptSecrets($overrides), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $exists = DB::table(self::TABLE)->where('module', $module)->exists();
        $row = ['settings' => $payload, 'updated_by' => $userId, 'updated_at' => now()];

        if ($exists) {
            DB::table(self::TABLE)->where('module', $module)->update($row);
        } else {
            DB::table(self::TABLE)->insert($row + ['module' => $module, 'created_at' => now()]);
        }

        self::forget($module);
    }

    public static function forget(string $module): void
    {
        Cache::forget(self::cacheKey($module));
    }

    /**
     * Remembered on the application instance, not in a static: a static outlives
     * the app (each test's fresh database, queue workers, Octane) and would skip
     * the check for a database that does not have the table yet.
     */
    public static function tableReady(): bool
    {
        $app = app();

        if ($app->bound(self::READY_KEY) && $app->make(self::READY_KEY) === true) {
            return true;
        }

        try {
            $ready = Schema::hasTable(self::TABLE);
        } catch (\Throwable) {
            return false;
        }

        // Only "exists" is remembered: a missing table is checked again (it appears after migrate).
        if ($ready) {
            $app->instance(self::READY_KEY, true);
        }

        return $ready;
    }

    /** Recursively encrypt string values under sensitive keys (already-encrypted values pass through). */
    public static function encryptSecrets(array $values, string $path = ''): array
    {
        foreach ($values as $key => $value) {
            $keyPath = $path === '' ? (string) $key : "{$path}.{$key}";

            if (is_array($value)) {
                $values[$key] = self::encryptSecrets($value, $keyPath);
            } elseif (is_string($value) && $value !== '' && ConfSecret::isSensitive((string) $key)) {
                $values[$key] = ConfSecret::encrypt($value);
            }
        }

        return $values;
    }

    /**
     * Old format (master_data_metas rows) → nested array:
     * "$SOCIAL_LOGIN~@O_AUTH_PROVIDERS~$GOOGLE~CLIENT_ID" = v
     *   → ['social_login' => ['o_auth_providers' => ['google' => ['client_id' => v]]]]
     * ($ = settings group, @ = list/map. IDENTIFIER keys are metadata and skipped;
     * JSON and "true"/"false" values are decoded.)
     *
     * @param  iterable<array{0: string, 1: mixed}|object>  $rows  [key, value] pairs or rows with meta_key/meta_value
     */
    public static function fromLegacyRows(iterable $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            [$key, $raw] = is_object($row) ? [$row->meta_key, $row->meta_value] : $row;
            $path = array_map(fn ($part) => strtolower(ltrim($part, '$@')), explode('~', (string) $key));

            if (end($path) === 'identifier') {
                continue;
            }

            $value = $raw;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_bool($decoded))) {
                    $value = $decoded;
                }
            }

            $cursor = &$values;
            foreach ($path as $i => $segment) {
                if ($i === count($path) - 1) {
                    $cursor[$segment] = $value;
                } else {
                    if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                        $cursor[$segment] = [];
                    }
                    $cursor = &$cursor[$segment];
                }
            }
            unset($cursor);
        }

        return $values;
    }

    private static function cacheKey(string $module): string
    {
        return 'module_settings:'.str_replace(['/', '\\', ':', ' '], '_', $module);
    }
}
