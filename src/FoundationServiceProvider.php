<?php

// =============================================================================
// FILE 2: FoundationServiceProvider.php (EMPTY - Everything Auto-Loaded!)
// =============================================================================

namespace Iquesters\Foundation;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Iquesters\Foundation\Http\Middleware\EnsureAdmin;
use Iquesters\Foundation\System\Providers\BaseServiceProvider;

class FoundationServiceProvider extends BaseServiceProvider
{
    // Everything auto-detected and auto-loaded by BaseServiceProvider!
    //
    // Automatically:
    // ✅ Detects module name: "foundation"
    // ✅ Finds PackageInfo: Iquesters\Foundation\Package\FoundationPackageInfo
    // ✅ Registers: ConfProvider, configs, providers, commands, middleware
    // ✅ Loads: routes, migrations, views
    // ✅ Publishes: config and view assets

    public function boot(): void
    {
        parent::boot();

        // Run the admin check before route-model binding, so non-admins get
        // 403 rather than learning whether a record exists (404).
        $kernel = $this->app->make(HttpKernel::class);
        if (method_exists($kernel, 'addToMiddlewarePriorityBefore')) {
            $kernel->addToMiddlewarePriorityBefore(SubstituteBindings::class, EnsureAdmin::class);
        }
    }
}
