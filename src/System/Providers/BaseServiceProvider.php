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
        // $this->loadPackageConfigs();
    }

    /* -------------------------------------------------
     | Boot Method
     |--------------------------------------------------*/
    public function boot(): void
    {
        $this->loadPackageViews();
        $this->loadPackageMigrations();
        $this->loadPackageRoutes();
        $this->registerPackageProviders();
        $this->registerPackageCommands();
        $this->registerPackageMiddleware();
        // $this->publishPackageAssets();
    }

    /* -------------------------------------------------
     | Package Info (Auto-Detection)
     |--------------------------------------------------*/
    protected function packageInfo(): PackageInfo
    {
        $moduleName = $this->detectModuleName();
        $packageInfoClass = $this->detectPackageInfoClass($moduleName);

        Log::info('Instantiating PackageInfo', [
            'module_name' => $moduleName,
            'package_info_class' => $packageInfoClass,
            'service_provider_class' => static::class,
        ]);

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
        Log::debug('Registering package ConfProvider', [
            'service_provider_class' => static::class,
        ]);

        $info = $this->packageInfo();
        $confClass = $info->getConfClass();
        $confModule = $info->getConfModule();

        if ($confClass && $confModule) {
            ConfProvider::register($confModule, $confClass);
            
            Log::info('ConfProvider registered', [
                'module' => $confModule,
                'conf_class' => $confClass,
            ]);
        } else {
            Log::debug('ConfProvider not registered (no config class or module found)', [
                'conf_class' => $confClass,
                'conf_module' => $confModule,
            ]);
        }
    }

    /* -------------------------------------------------
     | Package Registration Methods
     |--------------------------------------------------*/
    protected function registerPackageProviders(): void
    {
        Log::debug('Registering package providers', [
            'service_provider_class' => static::class,
        ]);

        $info = $this->packageInfo();
        $providers = $info->getProviders();

        if ($providers) {
            foreach ($providers as $provider) {
                $this->app->register($provider);
                Log::debug('Registered provider', ['provider' => $provider]);
            }
        }
    }

    protected function registerPackageCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        Log::debug('Registering package commands', [
            'service_provider_class' => static::class,
        ]);

        $info = $this->packageInfo();
        $commands = $info->getConsoleCommands();

        if ($commands) {
            $this->commands($commands);
            Log::debug('Registered commands', [
                'count' => count($commands),
                'commands' => array_map(function($cmd) {
                    return is_object($cmd) ? get_class($cmd) : $cmd;
                }, $commands)
            ]);
        }
    }

    protected function registerPackageMiddleware(): void
    {
        Log::debug('Registering package middleware', [
            'service_provider_class' => static::class,
        ]);

        $info = $this->packageInfo();
        $aliases = $info->getMiddlewareAliases();

        if ($aliases) {
            foreach ($aliases as $alias => $middleware) {
                $this->app['router']->aliasMiddleware($alias, $middleware);
                Log::debug('Registered middleware alias', [
                    'alias' => $alias,
                    'middleware' => $middleware
                ]);
            }
        }
    }

    /* -------------------------------------------------
     | Package Loading Methods
     |--------------------------------------------------*/
    protected function loadPackageViews(): void
    {
        Log::debug('Loading package views', [
            'service_provider_class' => static::class,
        ]);

        $info = $this->packageInfo();
        $paths = $info->getViewsPaths();
        $namespace = $info->getViewNamespace();

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->loadViewsFrom($path, $namespace);
                Log::debug('Loaded views', ['path' => $path, 'namespace' => $namespace]);
            }
        }
    }

    protected function loadPackageMigrations(): void
    {
        Log::debug('Loading package migrations', [
            'service_provider_class' => static::class,
        ]);

        $info = $this->packageInfo();
        $paths = $info->getMigrationsPaths();

        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->loadMigrationsFrom($path);
                Log::debug('Loaded migrations', ['path' => $path]);
            }
        }
    }

    protected function loadPackageRoutes(): void
    {
        Log::debug('Loading package routes', [
            'service_provider_class' => static::class,
        ]);

        $info = $this->packageInfo();
        $paths = $info->getRoutesPaths();

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->loadRoutesFrom($path);
                Log::debug('Loaded routes', ['path' => $path]);
            }
        }
    }

    protected function loadPackageConfigs(): void
    {
        Log::debug('Loading package configs', [
            'service_provider_class' => static::class,
        ]);

        $info = $this->packageInfo();
        $paths = $info->getConfigPaths();
        $confName = $info->getConfName();

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->mergeConfigFrom($path, $confName);
                Log::debug('Merged config', ['path' => $path, 'key' => $confName]);
            }
        }
    }

    protected function publishPackageAssets(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        Log::debug('Publishing package assets', [
            'service_provider_class' => static::class,
        ]);

        $info = $this->packageInfo();
        $assets = $info->getPublishableAssets();

        if ($assets) {
            foreach ($assets as $tag => $paths) {
                $this->publishes($paths, $tag);
                Log::debug('Registered publishable assets', [
                    'tag' => $tag,
                    'paths' => $paths
                ]);
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

        Log::debug('BaseServiceProvider::detectModuleName', [
            'service_provider_class' => static::class,
            'provider_class_name' => $providerClassName,
        ]);

        $moduleName = Str::kebab(Str::beforeLast($providerClassName, 'ServiceProvider'));

        Log::info('Module name detected successfully', [
            'service_provider_class' => static::class,
            'module_name' => $moduleName,
        ]);

        return $moduleName;
    }

    private function detectPackageInfoClass(string $moduleName): string
    {
        $resolver = new NamespaceResolver($moduleName);
        $packageInfoNamespace = $resolver->getPackageNamespace();
        $studlyModule = $resolver->getModuleNamespace();
        $packageInfoClass = "{$packageInfoNamespace}\\{$studlyModule}PackageInfo";

        Log::debug('PackageInfo class generated via NamespaceResolver', [
            'module_name' => $moduleName,
            'package_info_class' => $packageInfoClass,
        ]);

        return $packageInfoClass;
    }
}