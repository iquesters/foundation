<?php

namespace Iquesters\Foundation\System\Providers;

use Illuminate\Support\ServiceProvider;
use Iquesters\Foundation\System\Package\NamespaceResolver;
use Iquesters\Foundation\Support\ConfProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Iquesters\Foundation\System\Package\PackageInfo;

abstract class BaseServiceProvider extends ServiceProvider
{
    /* -------------------------------------------------
     | Register Method
     |--------------------------------------------------*/
    public function register(): void
    {
        $this->registerPackageConf();
        $this->registerSeederCommands();
    }

    /* -------------------------------------------------
     | Boot Method
     |--------------------------------------------------*/
    public function boot(): void
    {
        $this->loadPackageViews();
        $this->loadPackageMigrations();
        $this->loadPackageRoutes();
        $this->loadPackageApiRoutes();
        $this->registerPackageProviders();
        $this->registerPackageCommands();
        $this->registerPackageMiddleware();
    }

    /* -------------------------------------------------
     | Install-time hooks
     |--------------------------------------------------*/

    /**
     * Run $callback once the app has booted, but only during
     * `php artisan package:discover` (composer install/update). For one-time
     * install steps: publishing vendor files, patching the user model, etc.
     */
    protected function onPackageDiscover(callable $callback): void
    {
        if (!$this->app->runningInConsole() || ($_SERVER['argv'][1] ?? null) !== 'package:discover') {
            return;
        }

        $this->app->booted(fn () => $callback());
    }

    /* -------------------------------------------------
     | Package Info (Auto-Detection)
     |--------------------------------------------------*/

    /** Built once per provider: auto-discovery scans the module's folders. */
    private ?PackageInfo $packageInfoInstance = null;

    protected function packageInfo(): PackageInfo
    {
        return $this->packageInfoInstance ??= $this->makePackageInfo();
    }

    private function makePackageInfo(): PackageInfo
    {
        $moduleName = $this->detectModuleName();
        $packageInfoClass = $this->detectPackageInfoClass($moduleName);

if (!class_exists($packageInfoClass)) {
            throw new \RuntimeException("PackageInfo class not found: {$packageInfoClass}");
        }

        return new $packageInfoClass($moduleName);
    }

    /* -------------------------------------------------
     | Configuration Registration
     |--------------------------------------------------*/
    protected function registerPackageConf(): void
    {

        $info = $this->packageInfo();
        $confClass = $info->getConfClass();
        $confModule = $info->getConfModule();

        if ($confClass && $confModule) {
            ConfProvider::register($confModule, $confClass);
            
        } else {
            Log::debug('ConfProvider not registered (no config class or module found)', [
                'conf_class' => $confClass,
                'conf_module' => $confModule,
            ]);
        }
    }

    /* -------------------------------------------------
     | Seeder Command Registration (in register phase)
     |--------------------------------------------------*/
    protected function registerSeederCommands(): void
    {

        $info = $this->packageInfo();
        $bindings = $info->getSeederBindings();

        if ($bindings) {
            foreach ($bindings as $binding) {
                $bindingName = $binding['binding_name'] ?? $binding['command_class'];
                $commandClass = $binding['command_class'];
                $seederClass = $binding['seeder_class'];
                
                // Bind the SeederCommand with its required constructor parameter
                $this->app->bind($bindingName, function ($app) use ($commandClass, $seederClass) {
                    return new $commandClass($seederClass);
                });

            }
        }
    }

    /* -------------------------------------------------
     | Package Registration Methods
     |--------------------------------------------------*/
    protected function registerPackageProviders(): void
    {

        $info = $this->packageInfo();
        $providers = $info->getProviders();

        if ($providers) {
            foreach ($providers as $provider) {
                $this->app->register($provider);
            }
        }
    }

    protected function registerPackageCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

$info = $this->packageInfo();
        $commands = $info->getConsoleCommands();
        $bindings = $info->getSeederBindings();

        // Merge regular commands with seeder command classes
        $allCommands = $commands ?? [];
        if ($bindings) {
            foreach ($bindings as $binding) {
                $allCommands[] = $binding['binding_name'] ?? $binding['command_class'];
            }
        }

        if ($allCommands) {
            $this->commands($allCommands);
            Log::debug('Registered commands', [
                'count' => count($allCommands),
                'commands' => $allCommands
            ]);
        }
    }

    protected function registerPackageMiddleware(): void
    {

        $info = $this->packageInfo();
        $aliases = $info->getMiddlewareAliases();

        if ($aliases) {
            foreach ($aliases as $alias => $middleware) {
                $this->app['router']->aliasMiddleware($alias, $middleware);
            }
        }
    }

    /* -------------------------------------------------
     | Package Loading Methods
     |--------------------------------------------------*/
    protected function loadPackageViews(): void
    {

        $info = $this->packageInfo();
        $paths = $info->getViewsPaths();
        $namespace = $info->getViewNamespace();

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->loadViewsFrom($path, $namespace);
            }
        }
    }

    protected function loadPackageMigrations(): void
    {

        $info = $this->packageInfo();
        $paths = $info->getMigrationsPaths();

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
            }
        }
    }

    protected function loadPackageRoutes(): void
    {

        $info = $this->packageInfo();
        $paths = $info->getRoutesPaths();

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->loadRoutesFrom($path);
            }
        }
    }

    /**
     * /api/{module}/{version}/... for each version declared in composer.json
     * (extra.platform.api). Skipped when routes are cached.
     */
    protected function loadPackageApiRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $api = $this->packageInfo()->getApi();

        foreach ($api['versions'] ?? [] as $version => $routeFile) {
            \Iquesters\Foundation\Routing\ModuleApi::register($api['prefix'], $version, $routeFile);
        }
    }

    protected function loadPackageConfigs(): void
    {

        $info = $this->packageInfo();
        $paths = $info->getConfigPaths();
        $confName = $info->getConfName();

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->mergeConfigFrom($path, $confName);
            }
        }
    }

    protected function publishPackageAssets(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

$info = $this->packageInfo();
        $assets = $info->getPublishableAssets();

        if ($assets) {
            foreach ($assets as $tag => $paths) {
                $this->publishes($paths, $tag);
            }
        }
    }

    /* -------------------------------------------------
     | Auto-Detection Helpers
     |--------------------------------------------------*/
    private function detectModuleName(): string
    {
        $namespaceParts = explode('\\', static::class);
        $providerClassName = end($namespaceParts);

$moduleName = Str::kebab(Str::beforeLast($providerClassName, 'ServiceProvider'));

return $moduleName;
    }

    private function detectPackageInfoClass(string $moduleName): string
    {
        $resolver = new NamespaceResolver($moduleName);
        $packageInfoNamespace = $resolver->getPackageNamespace();
        $studlyModule = $resolver->getModuleNamespace();
        $packageInfoClass = "{$packageInfoNamespace}\\{$studlyModule}PackageInfo";

return $packageInfoClass;
    }
}
