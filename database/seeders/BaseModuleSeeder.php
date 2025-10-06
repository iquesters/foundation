<?php

namespace Iquesters\Foundation\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BaseModuleSeeder
 *
 * A reusable abstract seeder for Laravel modules.
 * Provides automatic insertion/updating of a module and its metadata.
 */
abstract class BaseModuleSeeder extends Seeder
{
    /**
     * Unique module name, used in the `modules` table.
     */
    protected string $moduleName;

    /**
     * Description for the module.
     */
    protected string $description;

    /**
     * Optional metadata array for the module.
     * Format: ['meta_key' => 'meta_value']
     */
    protected array $metas = [];

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        // Insert or update the module in the modules table
        DB::table('modules')->updateOrInsert(
            ['name' => $this->moduleName],
            [
                'uid'         => (string) Str::ulid(),
                'description' => $this->description,
                'status'      => 'active',
                'updated_at'  => now(),
                'created_at'  => now(),
            ]
        );

        // Insert module metadata if provided
        if (!empty($this->metas)) {
            $moduleId = DB::table('modules')->where('name', $this->moduleName)->value('id');

            foreach ($this->metas as $key => $value) {
                // 🔑 Ensure arrays/objects get stored as JSON strings
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                DB::table('module_metas')->updateOrInsert(
                    ['ref_parent' => $moduleId, 'meta_key' => $key],
                    [
                        'meta_value' => $value,
                        'status'     => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}