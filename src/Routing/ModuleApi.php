<?php

namespace Iquesters\Foundation\Routing;

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\System\Http\Middleware\RequestMiddleware;
use Iquesters\Foundation\System\Http\Middleware\ResponseMiddleware;

/**
 * Versioned module APIs: /api/{module}/{version}/...
 *
 * A module declares its API in composer.json (the single source of truth):
 *
 *     "extra": { "platform": { "api": {
 *         "prefix": "user-interface",
 *         "versions": { "v1": "routes/api.v1.php" }
 *     } } }
 *
 * BaseServiceProvider calls register() for each declared version. The route
 * file contains only the module's routes (no /api prefix, no version), with
 * names relative to "api.{module}.{version}.". Every version also gets
 * GET /api/{module}/{version} (status), and foundation serves GET /api (index).
 */
final class ModuleApi
{
    /** @var array<string, array<int, string>> module prefix => versions */
    private static array $registered = [];

    public static function register(string $prefix, string $version, string $routeFile): void
    {
        self::$registered[$prefix] = array_values(array_unique([...(self::$registered[$prefix] ?? []), $version]));

        Route::prefix("api/{$prefix}/{$version}")
            ->middleware(self::middleware($prefix, $version))
            ->name("api.{$prefix}.{$version}.")
            ->group(function () use ($prefix, $version, $routeFile) {
                // Wrapped by ResponseMiddleware into the standard envelope (response_schema.data).
                Route::get('/', fn () => response()->json([
                    'module' => $prefix,
                    'version' => $version,
                ]))->name('status');

                require $routeFile;
            });
    }

    /** Modules and their versions, for GET /api. */
    public static function registered(): array
    {
        return self::$registered;
    }

    /**
     * Base stack + per-module extras from config/foundation.php:
     *   api.middleware.{module}           => [...]   (all versions)
     *   api.rate_limits.{module}.{version} => 'requests,minutes'
     */
    private static function middleware(string $prefix, string $version): array
    {
        $middleware = ['api', RequestMiddleware::class, ResponseMiddleware::class];

        foreach ((array) config("foundation.api.middleware.{$prefix}", []) as $extra) {
            $middleware[] = $extra;
        }

        if ($limit = config("foundation.api.rate_limits.{$prefix}.{$version}")) {
            $middleware[] = 'throttle:' . $limit;
        }

        return $middleware;
    }
}
