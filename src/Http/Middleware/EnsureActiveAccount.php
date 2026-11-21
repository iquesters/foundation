<?php

namespace Iquesters\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Shared lifecycle check after authentication; public guest/service calls pass. */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        // On legacy deployments users.status may not exist yet. Keep the
        // previous authorization semantics until the schema/backfill cutover
        // has been explicitly approved; never infer readiness from a code merge.
        if (! config('foundation.account_state_enforcement_enabled', false)) {
            return $next($request);
        }

        $user = $request->user();
        // A disabled user must still be able to terminate their web session.
        if ($user && ! $request->routeIs('logout')
            && (! method_exists($user, 'getAttribute') || strtolower((string) $user->getAttribute('status')) !== 'active')) {
            Log::warning('account_security.inactive_session_denied', ['user_id' => $user->getAuthIdentifier()]);
            abort(401, 'Account is unavailable.');
        }

        return $next($request);
    }
}
