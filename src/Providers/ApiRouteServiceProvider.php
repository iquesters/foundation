<?php

namespace Iquesters\Foundation\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Iquesters\Foundation\Routing\PackageDiscovery;
use Iquesters\Foundation\Support\ConfProvider;
use Illuminate\Support\Str;
use Iquesters\Foundation\System\Http\Middleware\RequestMiddleware;
use Iquesters\Foundation\System\Http\Middleware\ResponseMiddleware;
use Iquesters\Foundation\System\Traits\Loggable;


class ApiRouteServiceProvider extends ServiceProvider
{
    use Loggable;

    public function boot(): void
    {
        // Runs on every request (route registration), so keep it at debug level.
        static::logDebug('Registering platform API routes');
        Route::prefix('api')
            ->middleware(['api', RequestMiddleware::class, ResponseMiddleware::class])
            ->group(function (): void {

                /**
                 * --------------------------------------------------------------------------
                 * /api → list platform versions
                 * --------------------------------------------------------------------------
                 */
                Route::get('/', function () {
                    return response()->json([
                        'success' => true,
                        'platform_versions' => config('foundation.platform_versions', ['v1']),
                    ]);
                });

                foreach (config('foundation.platform_versions', []) as $platformVersion) {
                    $platformRouteFile =
                        __DIR__ . "/../../routes/platform.api.{$platformVersion}.php";

                    static::logDebug(
                        '[ApiRouteServiceProvider] Loading platform file name',
                        ['platform_filename' => $platformRouteFile]
                    );

                    if (! file_exists($platformRouteFile)) {
                        static::logWarning(
                            '[ApiRouteServiceProvider] Platform route file not found',
                            ['platform_version' => $platformVersion]
                        );
                        continue;
                    }

                    static::logDebug(
                        '[ApiRouteServiceProvider] Loading platform routes',
                        ['platform_version' => $platformVersion]
                    );

                    Route::prefix($platformVersion)
                        // ->middleware('validate.platform.version')
                        ->group(function () use ($platformRouteFile, $platformVersion): void {

                            /**
                             * ----------------------------------------------------------------------
                             * /api/{platform_version}
                             * ----------------------------------------------------------------------
                             */
                            $this->loadRoutesFrom($platformRouteFile);
                            static::logDebug('-------------------------------------------');
                            foreach (PackageDiscovery::getDiscoveredPackageNames() as $packageName) {
                                $conf = ConfProvider::from($packageName);
                                $conf->ensureLoaded();

                                if (! property_exists($conf, 'api_conf') || ! $conf->api_conf) {
                                    continue;
                                }

                                $apiConf = $conf->api_conf;

                                static::logDebug(
                                    '[ApiRouteServiceProvider] Loading package API',
                                    [
                                        'platform_version' => $platformVersion,
                                        'package' => $packageName,
                                        'prefix' => $apiConf->prefix,
                                    ]
                                );

                                Route::prefix($apiConf->prefix)
                                    ->group(function () use (
                                        $platformVersion,
                                        $packageName,
                                        $apiConf
                                    ): void {

                                        /**
                                         * ------------------------------------------------------------------
                                         * /api/{platform}/{package}
                                         * ------------------------------------------------------------------
                                         */
                                        Route::get('/', function () use ($packageName, $apiConf) {
                                            return response()->json([
                                                'success' => true,
                                                'package' => $packageName,
                                                'available_versions' => collect($apiConf->api_versions)
                                                    ->pluck('version')
                                                    ->values(),
                                            ]);
                                        });

                                        foreach ($apiConf->api_versions as $apiVersion) {
                                            static::logDebug(
                                                '[ApiRouteServiceProvider] Preparing to load API version',
                                                [
                                                    'package' => $packageName,
                                                    'api_version' => $apiVersion->version,
                                                ]
                                            );
                                            $package = Str::after($packageName, '/');

                                            $routeFile =
                                                __DIR__ . "/../../../{$package}/{$apiVersion->file_name}";

                                            static::logDebug(
                                                '[ApiRouteServiceProvider] Loading package file name',
                                                ['package_filename' => $routeFile]
                                            );

                                            if (! file_exists($routeFile)) {
                                                static::logWarning(
                                                    '[ApiRouteServiceProvider] API version route file not found',
                                                    [
                                                        'package' => $packageName,
                                                        'api_version' => $apiVersion->version,
                                                    ]
                                                );
                                                continue;
                                            }

                                            Route::prefix($apiVersion->version)
                                                // ->middleware($apiConf->middleware ?? [])
                                                ->group(function () use (
                                                    $platformVersion,
                                                    $packageName,
                                                    $apiVersion,
                                                    $routeFile
                                                ): void {

                                                    /**
                                                     * ----------------------------------------------------------
                                                     * /api/{platform}/{package}/{package_version}
                                                     * ----------------------------------------------------------
                                                     */
                                                    Route::get('/', function () use (
                                                        $platformVersion,
                                                        $packageName,
                                                        $apiVersion
                                                    ) {
                                                        return response()->json([
                                                            'success' => true,
                                                            'message' => 'Package API enabled and running',
                                                            'platform_version' => $platformVersion,
                                                            'package' => $packageName,
                                                            'package_version' => $apiVersion->version,
                                                        ]);
                                                    });

                                                    static::logDebug(
                                                        '[ApiRouteServiceProvider] Loading API routes',
                                                        [
                                                            'platform_version' => $platformVersion,
                                                            'package' => $packageName,
                                                            'api_version' => $apiVersion->version,
                                                        ]
                                                    );

                                                    $this->loadRoutesFrom($routeFile);
                                                });
                                        }
                                    });
                            }
                        });
                }
            });

    }
}