<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Iquesters\Foundation\Http\Middleware\EnsureAdmin;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Per-entity permissions for the generic entity API:
 *   entity.{table}.view | create | update | delete
 *
 * Admin roles (foundation conf admin_roles, see EnsureAdmin::roles()) are always allowed.
 */
final class EntityPermission
{
    public const ACTIONS = ['view', 'create', 'update', 'delete'];

    public static function name(string $table, string $action): string
    {
        return "entity.{$table}.{$action}";
    }

    /** @return string[] */
    public static function names(string $table): array
    {
        return array_map(fn ($action) => self::name($table, $action), self::ACTIONS);
    }

    /** Create the four permissions for a table (idempotent). */
    public static function ensureFor(string $table, string $guard = 'web'): void
    {
        foreach (self::names($table) as $name) {
            Permission::findOrCreate($name, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function allows(?Authenticatable $user, string $table, string $action): bool
    {
        if (! $user || ! in_array($action, self::ACTIONS, true)) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(EnsureAdmin::roles())) {
            return true;
        }

        if (! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        try {
            return $user->hasPermissionTo(self::name($table, $action));
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
