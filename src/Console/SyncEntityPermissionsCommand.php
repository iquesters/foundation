<?php

namespace Iquesters\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Iquesters\Foundation\Support\EntityPermission;

/**
 * Creates entity.{table}.view/create/update/delete for every registered entity
 * (publish does this for new entities; run once for existing ones).
 */
class SyncEntityPermissionsCommand extends Command
{
    protected $signature = 'entity:sync-permissions';

    protected $description = 'Create per-entity permissions (entity.{table}.view|create|update|delete) for all registered entities';

    public function handle(): int
    {
        $tables = DB::table('entity_metas')
            ->whereIn('meta_key', ['table_name', 'published_table_name'])
            ->whereNotNull('meta_value')
            ->where('meta_value', '<>', '')
            ->distinct()
            ->pluck('meta_value');

        foreach ($tables as $table) {
            EntityPermission::ensureFor($table);
            $this->line("  {$table}: " . implode(', ', EntityPermission::names($table)));
        }

        $this->info($tables->count() . ' entity table(s) synced.');

        return self::SUCCESS;
    }
}
