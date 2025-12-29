<?php

namespace Iquesters\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ValidatePlatformVersion
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Starting with ######
        Log::debug("##################################################");
        Log::debug("Start of ValidatePlatformVersionMiddleware...");
        Log::debug("--------------------------------------------------");
        
        Log::debug('Request', [
            'url'     => $request->fullUrl(),
            'method'  => $request->method(),
            'headers' => collect($request->headers->all())
                ->except(['authorization', 'cookie'])
                ->toArray(),
            'body'    => $request->except([
                'password',
                'password_confirmation',
                'token',
                'access_token',
            ]),
        ]);

        $version = $request->route('platform_version');

        Log::debug("Extracted platform_version = " . ($version ?? 'NULL'));
        Log::debug("--------------------------------------------------");

        // Defensive: platform_version must exist
        if (! $version) {
            Log::debug("platform_version missing.");
            Log::debug("End of ValidatePlatformVersionMiddleware.");
            Log::debug("==================================================");

            throw new NotFoundHttpException('Missing platform version.');
        }

        $allowed = config('foundation.platform_versions', []);

        Log::debug("Allowed platform versions:");
        Log::debug($allowed);
        Log::debug("--------------------------------------------------");

        if (! in_array($version, $allowed, true)) {
            Log::debug("platform_version '{$version}' is NOT supported.");
            Log::debug("End of ValidatePlatformVersionMiddleware.");
            Log::debug("==================================================");

            throw new NotFoundHttpException(
                "Platform API version '{$version}' is not supported."
            );
        }

        Log::debug("platform_version '{$version}' is valid.");
        Log::debug("--------------------------------------------------");
        Log::debug("Forwarding to next...");
        Log::debug("End of ValidatePlatformVersionMiddleware.");
        Log::debug("==================================================");

        return $next($request);
    }
}