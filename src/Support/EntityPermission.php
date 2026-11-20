<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Iquesters\Foundation\Http\Middleware\EnsureAdmin;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Per-entity permissions for the generic entity API:
 *   entity.{table}.view | create | update | delete
 *
 * Transitional generic entity operations are platform-only: their polymorphic ownership is not covered by the generic column scope. Dedicated domain routes use scoped policy.
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

        // Generic repository writes bypass Eloquent's shared password/revocation
        // unit of work. User mutations must use the dedicated account/admin routes.
        if (strtolower($table) === 'users' && $action !== 'view') {
            return false;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(EnsureAdmin::roles(), 'web')) {
            return true;
        }

        // The legacy generic entity router accepts table and record IDs but
        // has no tenant ownership contract. Global entity permissions cannot
        // substitute for that contract. Restrict until Phase B2 supplies a
        // scoped resource policy; dedicated Inbox/account/admin routes remain.
        return false;
    }
}
