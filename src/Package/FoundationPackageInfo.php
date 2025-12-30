<?php

namespace Iquesters\Foundation\Package;

use Iquesters\Foundation\System\Package\PackageInfo;
use Iquesters\Foundation\Console\ApiRouteDoctorCommand;
use Iquesters\Foundation\Console\OpenApiGenerateCommand;

class FoundationPackageInfo extends PackageInfo
{
    protected string $module_name = 'foundation';

    protected bool $load_laravel_native_config = true;

    protected string $seeder_class = 'FoundationSeeder';
    
    protected ?array $console_commands = [
        ApiRouteDoctorCommand::class,
        OpenApiGenerateCommand::class,
    ];
}