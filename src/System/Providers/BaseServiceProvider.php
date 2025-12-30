<?php

namespace Iquesters\Foundation\System\Providers;

use Illuminate\Support\ServiceProvider;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Console\SeederCommand;
use Iquesters\Foundation\System\Package\PackageInfo;

abstract class BaseServiceProvider extends ServiceProvider
{
    abstract protected function packageInfo(): PackageInfo;
    

    public function boot(): void
    {
        // Routes
        $this->loadPackageRoutes();
        
        // Migrations
        $this->loadPackageMigrations();

        // Views
        $this->loadPackageViews();

        // Middlewares
        $this->loadPackageMiddlewares();
        
        // Seeder
        $this->loadPackageSeeder();
        
        // Console Command
        $this->loadPackageConsoleCommands();

        // // Optional service provider
        // foreach ($this->serviceProviders() as $serviceProvider) {
        //      $this->app->register($serviceProvider);
        // }

    }
    
    public function register(): void
    {
        $this->registerConf();
    }
    
    private function registerConf(): void
    {
        $info = $this->packageInfo();

        // Custom config system
        ConfProvider::register(
            $info->moduleName(),
            $info->configClass()
        );
        
        // Laravel native config
        if ($info->shouldLoadLaravelNativeConfig()) {
            $this->mergeConfigFrom(
                $info->laravelConfigPath(),
                $info->laravelConfigName()
            );
        }
    }
    
    protected function loadPackageRoutes(): void
    {
        foreach ($this->packageInfo()->routes() as $routeFile) {
            if (is_file($routeFile)) {
                $this->loadRoutesFrom($routeFile);
            }
        }
    }

    protected function loadPackageMigrations(): void
    {
        $path = $this->packageInfo()->migrationsPath();

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }

    protected function loadPackageViews(): void
    {
        $info = $this->packageInfo();

        $path = $info->viewsPath();

        if (is_dir($path)) {
            $this->loadViewsFrom(
                $path,
                $info->viewNamespace()
            );
        }
    }
    
    protected function loadPackageMiddlewares(): void
    {
        $middlewares = $this->packageInfo()->middlewares();

        if (empty($middlewares)) {
            return;
        }

        $router = $this->app['router'];

        foreach ($middlewares as $alias => $class) {
            $router->aliasMiddleware($alias, $class);
        }
    }

    protected function loadPackageSeeder(): void
    {
        $seederClass = $this->packageInfo()->seederClass();

        if ($seederClass && $this->app->runningInConsole()) {
            $this->commands([
                new SeederCommand($seederClass),
            ]);
        }
    }
    
    protected function loadPackageConsoleCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $commands = $this->packageInfo()->consoleCommands();

        if (!empty($commands)) {
            $this->commands($commands);
        }
    }

}