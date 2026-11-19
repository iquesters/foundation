<?php

namespace Iquesters\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Iquesters\Foundation\Services\BusinessEntity\BusinessEntityService;

/**
 * Creates or updates business entities from definition files, so they can be
 * kept in version control and deployed like migrations:
 *
 *     database/business-entities/customer-profile.json
 *     {"name": "Customer Profile", "slug": "customer-profile", "definition": {...}}
 *
 * Entities are named by table ("entity": "customers"), because uids differ
 * per install; business-entity:export writes files in this format. Files are
 * matched by slug. Each one is validated like an admin save; nothing changes
 * the database structure.
 */
class BusinessEntitySyncCommand extends Command
{
    protected $signature = 'business-entity:sync
        {path? : A definition file or a folder of them (default: database/business-entities)}
        {--dry-run : Only validate the files}';

    protected $description = 'Create or update business entities from JSON definition files';

    public function handle(BusinessEntityService $service): int
    {
        $path = (string) ($this->argument('path') ?? database_path('business-entities'));
        $files = File::isDirectory($path) ? File::glob(rtrim($path, '/\\').'/*.json') : (File::isFile($path) ? [$path] : []);

        if ($files === []) {
            $this->warn("No definition files found in {$path}.");

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($files as $file) {
            $name = basename($file);

            try {
                $data = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($data)) {
                    throw new InvalidArgumentException('The file is not a JSON object.');
                }

                if ($this->option('dry-run')) {
                    $service->validate((array) ($data['definition'] ?? []));
                    $this->line("  <info>valid</info>   {$name}");

                    continue;
                }

                [$businessEntity, $action] = $service->upsertFromFile($data);
                $this->line("  <info>{$action}</info> {$name} → {$businessEntity->slug}");
            } catch (\JsonException|InvalidArgumentException $e) {
                $failed++;
                $this->line("  <error>failed</error>  {$name}: {$e->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
