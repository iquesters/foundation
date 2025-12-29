<?php

namespace Iquesters\Foundation\Console;

use Illuminate\Console\Command;
use Iquesters\Foundation\Routing\ApiRouteRegistry;

class ApiRouteDoctorCommand extends Command
{
    protected $signature = 'api:doctor';
    protected $description = 'Diagnose API routing issues';

    public function handle(): int
    {
        $routes = ApiRouteRegistry::all();

        $this->info('Registered API routes: '.count($routes));

        foreach ($routes as $route => $pkg) {
            $this->line("{$route} → {$pkg}");
        }

        return self::SUCCESS;
    }
}