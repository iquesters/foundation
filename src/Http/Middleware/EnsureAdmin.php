<?php

namespace Iquesters\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Iquesters\Foundation\Enums\Module;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\System\Traits\Loggable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to users holding one of the admin roles from the
 * foundation conf (DB setting `admin_roles`). Auto-registered as `ensure.admin`.
 *
 * Fails closed: a guest, or a user model without role support, is denied.
 */
class EnsureAdmin
{
    use Loggable;

    public const DEFAULT_ROLES = ['super-admin', 'admin'];

    /** Always allowed, so a bad admin_roles value can't lock every admin out. */
    public const ALWAYS_ALLOWED = 'super-admin';

    public static function roles(): array
    {
        try {
            $configured = (string) ConfProvider::from(Module::FOUNDATION)->admin_roles;
        } catch (\Throwable) {
            $configured = '';
        }

        $roles = array_values(array_filter(array_map('trim', explode(',', $configured))));

        if ($roles === []) {
            $roles = self::DEFAULT_ROLES;
        }

        return array_values(array_unique([self::ALWAYS_ALLOWED, ...$roles]));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $roles = self::roles();

        $allowed = $user
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole($roles);

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
