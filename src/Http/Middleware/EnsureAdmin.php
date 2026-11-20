<?php

namespace Iquesters\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Iquesters\Foundation\System\Traits\Loggable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts global administration to the web-guard super-admin role.
 * Auto-registered as `ensure.admin`; tenant admin is a separate scoped policy.
 *
 * Fails closed: a guest, or a user model without role support, is denied.
 */
class EnsureAdmin
{
    use Loggable;

    public const DEFAULT_ROLES = ['super-admin'];

    /** Always allowed, so a bad admin_roles value can't lock every admin out. */
    public const ALWAYS_ALLOWED = 'super-admin';

    public static function roles(): array
    {
        // Global settings cannot promote a tenant admin to platform authority.
        // Tenant administration uses TenantAuthorization and explicit membership.
        return self::DEFAULT_ROLES;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $roles = self::roles();

        $allowed = $user
            && strtolower((string) $user->getAttribute('status')) === 'active'
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($roles, 'web');

        if (! $allowed) {
            static::logWarning('Admin access denied', [
                'user_id' => $user?->getAuthIdentifier(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
