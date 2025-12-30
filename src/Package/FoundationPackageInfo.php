<?php

namespace Iquesters\Foundation\Package;

use Iquesters\Foundation\System\Package\PackageInfo;
use Iquesters\Foundation\Console\ApiRouteDoctorCommand;
use Iquesters\Foundation\Console\OpenApiGenerateCommand;

class FoundationPackageInfo extends PackageInfo
{
    protected function definePackageInfo(): void
    {
        $this->laravel_config_name = 'foundation';

        $this->specific_providers = [
            FoundationServiceProvider::class,
        ];

        $this->specific_commands = [
            SeederCommand::class,
        ];
    }
}
