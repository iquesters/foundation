<?php

namespace Iquesters\Foundation\System\Providers;

use Illuminate\Support\ServiceProvider;
use Iquesters\Foundation\System\Package\NamespaceResolver;
use Illuminate\Support\Str;

abstract class BaseServiceProvider extends ServiceProvider
{
    /* -------------------------------------------------
     | Abstract Methods (Optional Override)
     |--------------------------------------------------*/

    /**
     * Get package info instance (auto-detected if not overridden)
     */
    /**
     * Get package info instance (auto-detected)
     */
    protected function packageInfo(): PackageInfo
    {
        $moduleName = $this->detectModuleName();
        $packageInfoClass = $this->detectPackageInfoClass($moduleName);

        \Log::info('Instantiating PackageInfo', [
            'module_name' => $moduleName,
            'package_info_class' => $packageInfoClass,
            'service_provider_class' => static::class,
        ]);

        if (!class_exists($packageInfoClass)) {
            throw new \RuntimeException("PackageInfo class not found: {$packageInfoClass}");
        }

        $packageInfo = new $packageInfoClass($moduleName);

        \Log::info('PackageInfo instantiated successfully', [
            'module_name' => $moduleName,
            'package_info_class' => $packageInfoClass,
            'package_namespace' => $packageInfo->getNamespace(),
        ]);

        return $packageInfo;
    }

    /* -------------------------------------------------
     | Register Method
     |--------------------------------------------------*/

    public function register(): void
    {
        $this->registerPackageProviders();
        $this->registerPackageCommands();
        $this->loadPackageConfigs();
    }

    /* -------------------------------------------------
     | Boot Method
     |--------------------------------------------------*/

    public function boot(): void
    {
        $this->loadPackageViews();
        $this->loadPackageMigrations();
        $this->loadPackageRoutes();
    }

    /* -------------------------------------------------
     | Package Registration Methods
     |--------------------------------------------------*/

    /**
     * Register specific providers from package info
     */
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

    /**
     * Register specific console commands from package info
     */
    protected function registerPackageCommands(): void
    {
        $info = $this->packageInfo();
        $commands = $info->getConsoleCommands();

        if ($commands) {
            $this->commands($commands);
        }
    }

    /* -------------------------------------------------
     | Package Loading Methods
     |--------------------------------------------------*/

    /**
     * Load ALL views from default + custom paths
     */
    protected function loadPackageViews(): void
    {
        $info = $this->packageInfo();
        $paths = $info->getViewsPaths();
        $namespace = $info->view_namespace;

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->loadViewsFrom($path, $namespace);
            }
        }
    }

    /**
     * Load ALL migrations from default + custom paths
     */
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

    /**
     * Load ALL routes from default + custom paths
     */
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
     * Merge ALL configs from default + custom paths
     */
    protected function loadPackageConfigs(): void
    {
        $info = $this->packageInfo();
        $paths = $info->getConfigPaths();

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->mergeConfigFrom($path, $info->conf_name);
            }
        }
    }

    /* -------------------------------------------------
     | Auto-Detection Helpers
     |--------------------------------------------------*/

    /**
     * Extract module name from current namespace
     * Iquesters\Foundation\Providers → 'foundation'
     */
    private function detectModuleName(): string
    {
        $namespaceParts = explode('\\', static::class);
        $providerClassName = end($namespaceParts); // Last part: FoundationServiceProvider

        \Log::debug('BaseServiceProvider::detectModuleName', [
            'service_provider_class' => static::class,
            'provider_class_name' => $providerClassName,
            'namespace_parts' => $namespaceParts,
        ]);

        // Extract module name from provider class name
        // FoundationServiceProvider → foundation
        // UserInterfaceServiceProvider → user-interface
        $moduleName = Str::kebab(Str::beforeLast($providerClassName, 'ServiceProvider'));

        \Log::info('Module name detected successfully', [
            'service_provider_class' => static::class,
            'provider_class_name' => $providerClassName,
            'module_name' => $moduleName,
            'namespace_parts' => $namespaceParts,
        ]);

        return $moduleName;
    }

    /**
     * Generate PackageInfo class name using NamespaceResolver
     * foundation → Iquesters\Foundation\System\Package\FoundationPackageInfo
     */
    private function detectPackageInfoClass(string $moduleName): string
    {
        $resolver = new NamespaceResolver($moduleName);
        $packageInfoNamespace = $resolver->getPackageNamespace();

        $studlyModule = $resolver->getModuleNamespace(); // Foundation
        $packageInfoClass = "{$packageInfoNamespace}\\{$studlyModule}PackageInfo";

        \Log::debug('PackageInfo class generated via NamespaceResolver', [
            'module_name' => $moduleName,
            'base_namespace' => $resolver->getNamespace(),
            'package_namespace' => $packageInfoNamespace,
            'studly_module' => $studlyModule,
            'package_info_class' => $packageInfoClass,
        ]);

        if (!class_exists($packageInfoClass)) {
            \Log::warning('PackageInfo class not found', [
                'expected_class' => $packageInfoClass,
                'module_name' => $moduleName,
            ]);
        }

        return $packageInfoClass;
    }
}
