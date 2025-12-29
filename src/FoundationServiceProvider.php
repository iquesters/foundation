<?php

namespace Iquesters\Foundation;

use Illuminate\Support\ServiceProvider;
use Iquesters\Foundation\Providers\ApiRouteServiceProvider;
use Iquesters\Foundation\Console\ApiRouteDoctorCommand;
use Iquesters\Foundation\Console\OpenApiGenerateCommand;
use Iquesters\Foundation\Http\Middleware\ValidatePlatformVersion;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\Enums\Module;
use Illuminate\Console\Command;
use Iquesters\Foundation\Database\Seeders\FoundationSeeder;
use Iquesters\Foundation\Config\FoundationConf;

class FoundationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register User Interface configuration
        ConfProvider::register(Module::FOUNDATION, FoundationConf::class);

        // Merge package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/foundation.php', 'foundation');
        
        $this->registerSeedCommand();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load web routes (non-API)
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load package views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'foundation');

        /*
        |--------------------------------------------------------------------------
        | API Routing System
        |--------------------------------------------------------------------------
        */

        // Register platform API router
        $this->app->register(ApiRouteServiceProvider::class);

        // Register platform version validator middleware
        $this->app['router']->aliasMiddleware(
            'validate.platform.version',
            ValidatePlatformVersion::class
        );

        /*
        |--------------------------------------------------------------------------
        | Console Commands
        |--------------------------------------------------------------------------
        */

        if ($this->app->runningInConsole()) {
            $this->commands([
                ApiRouteDoctorCommand::class,
                OpenApiGenerateCommand::class,
                'command.foundation.seed'
            ]);
        }
    }
    
    protected function registerSeedCommand(): void
    {
        $this->app->singleton('command.foundation.seed', function ($app) {
            return new class extends Command {
                protected $signature = 'foundation:seed';
                protected $description = 'Seed Foundation module data';

                public function handle()
                {
                    $this->info('Running Foundation Seeder...');
                    $seeder = new FoundationSeeder();
                    $seeder->setCommand($this);
                    $seeder->run();
                    $this->info('Foundation seeding completed!');
                    return 0;
                }
            };
        });
    }
}