<?php

namespace Iquesters\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to users holding one of the configured admin roles
 * (config('foundation.admin_roles')). Auto-registered as `ensure.admin`.
 *
 * Fails closed: a guest, or a user model without role support, is denied.
 */
class EnsureAdmin
{
    public const DEFAULT_ROLES = ['super-admin', 'admin'];

    public static function roles(): array
    {
        return (array) config('foundation.admin_roles', self::DEFAULT_ROLES);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $roles = self::roles();

        $allowed = $user
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($roles);

        if (! $allowed) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
