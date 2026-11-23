<?php

namespace Iquesters\Foundation;

use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Middleware\EnsureActiveAccount;
use Iquesters\Foundation\Http\Middleware\EnsureAdmin;
use Iquesters\Foundation\Services\Entity\EntitySchemaResolver;
use Iquesters\Foundation\Support\Assets;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityModels;
use Iquesters\Foundation\Support\BusinessEntity\Relations\RelationTypes;
use Iquesters\Foundation\System\Providers\BaseServiceProvider;

/**
 * Routes, views, migrations, commands, middleware aliases and the conf are
 * auto-loaded by BaseServiceProvider (see Package\FoundationPackageInfo).
 */
class FoundationServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        parent::register();

        // Code-level settings with no DB equivalent (admin roles, API limits).
        $this->mergeConfigFrom(__DIR__.'/../config/foundation.php', 'foundation');

        // Entity API: table introspection is memoized per request.
        $this->app->scoped(EntitySchemaResolver::class);

        // Business entities: link types (modules add more with the tag) and referenceable models.
        $this->app->singleton(RelationTypes::class, fn ($app) => new RelationTypes($app->tagged(RelationTypes::TAG)));
        $this->app->singleton(BusinessEntityModels::class);
    }

    public function boot(): void
    {
        parent::boot();

        // public/ (admin screen scripts and styles), see Support\Assets.
        Assets::registerRoutes();

        // 'laravel-assets' is re-published by the app after every `composer update`.
        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/foundation'),
        ], ['laravel-assets', 'foundation-assets']);

        // GET /api: the module APIs and their versions (/api/{module}/{version}).
        // Built from the route list, so it also works with cached routes.
        if (! $this->app->routesAreCached()) {
            Route::middleware('api')->get('api', function () {
                $modules = [];

                foreach (Route::getRoutes()->getRoutes() as $route) {
                    if (preg_match('/^api\.(.+)\.(v[^.]+)\.status$/', (string) $route->getName(), $m)) {
                        $modules[$m[1]][] = $m[2];
                    }
                }

                return response()->json(['success' => true, 'modules' => $modules]);
            })->name('api.index');
        }

        // Run the admin check before route-model binding, so non-admins get
        // 403 rather than learning whether a record exists (404).
        $kernel = $this->app->make(HttpKernel::class);
        // Covers existing browser and Sanctum paths without duplicating domain
        // auth services. Guest callbacks and internal service calls have no user.
        $kernel->appendMiddlewareToGroup('web', EnsureActiveAccount::class);
        $kernel->appendMiddlewareToGroup('api', EnsureActiveAccount::class);
        $kernel->addToMiddlewarePriorityAfter(
            AuthenticatesRequests::class,
            EnsureActiveAccount::class
        );
        if (method_exists($kernel, 'addToMiddlewarePriorityBefore')) {
            $kernel->addToMiddlewarePriorityBefore(SubstituteBindings::class, EnsureAdmin::class);
        }
    }
}
