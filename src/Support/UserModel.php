<?php

namespace Iquesters\Foundation\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the application's user model from config (like Spatie and Sanctum
 * do), so modules never hard-code App\Models\User.
 */
final class UserModel
{
    /** @return class-string<Model> */
    public static function name(): string
    {
        return config('auth.providers.users.model') ?: 'App\\Models\\User';
    }

    public static function query(): Builder
    {
        return self::name()::query();
    }

    /** Resolve a route parameter (id, or an already-bound model) to a user. */
    public static function findOrFail(mixed $user): Model
    {
        return $user instanceof Model ? $user : self::query()->findOrFail($user);
    }
}
