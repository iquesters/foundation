<?php

namespace Iquesters\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Services\BusinessEntity\BusinessEntityService;

/**
 * Writes business entities as definition files for business-entity:sync
 * (entities named by table instead of uid, so the files work on any install).
 */
class BusinessEntityExportCommand extends Command
{
    protected $signature = 'business-entity:export
        {slug? : Only this business entity (default: all active ones)}
        {--path= : Folder to write to (default: database/business-entities)}';

    protected $description = 'Export business entities as JSON definition files';

    public function handle(BusinessEntityService $service): int
    {
        $folder = (string) ($this->option('path') ?: database_path('business-entities'));
        $query = BusinessEntity::active();

        if ($slug = $this->argument('slug')) {
            $query->where('slug', $slug);
        }

        $businessEntities = $query->orderBy('slug')->get();

        if ($businessEntities->isEmpty()) {
            $this->warn('No business entity to export.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($folder);

        foreach ($businessEntities as $businessEntity) {
            $file = rtrim($folder, '/\\').DIRECTORY_SEPARATOR.$businessEntity->slug.'.json';
            File::put($file, json_encode($service->toFile($businessEntity), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
            $this->line("  <info>exported</info> {$businessEntity->slug} → {$file}");
        }

        return self::SUCCESS;
    }
}
