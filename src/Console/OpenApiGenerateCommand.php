<?php

namespace Iquesters\Foundation\Console;

use Illuminate\Console\Command;
use Iquesters\Foundation\OpenApi\OpenApiGenerator;

class OpenApiGenerateCommand extends Command
{
    protected $signature = 'api:openapi';
    protected $description = 'Generate OpenAPI specification';

    public function handle(): int
    {
        $spec = (new OpenApiGenerator())->generate();

        file_put_contents(
            storage_path('openapi.json'),
            json_encode($spec, JSON_PRETTY_PRINT)
        );

        $this->info('OpenAPI spec generated.');

        return self::SUCCESS;
    }
}