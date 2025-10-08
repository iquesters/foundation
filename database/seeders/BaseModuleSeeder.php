<?php

namespace Iquesters\Foundation\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * BaseModuleSeeder
 *
 * A reusable abstract seeder for Laravel modules.
 * Handles:
 *   - Module creation/updating
 *   - Module metadata
 *   - Module-specific permissions
 *   - Super-admin role creation & permission assignment
 */
abstract class BaseModuleSeeder extends Seeder
{
    /**
     * Unique module name (used in `modules` table)
     */
    protected string $moduleName;

    /**
     * Description of the module
     */
    protected string $description;

    /**
     * Optional metadata for the module
     * Format: ['meta_key' => 'meta_value']
     */
    protected array $metas = [];

    /**
     * Optional permissions for the module
     */
    protected array $permissions = [];

    /**
     * Guard name for Spatie roles/permissions
     */
    protected string $guardName = 'web';

    /**
     * Run the seeder
     */
    public function run(): void
    {
        // 1️⃣ Insert or update the module in the `modules` table
        DB::table('modules')->updateOrInsert(
            ['name' => $this->moduleName],
            [
                'uid' => (string) Str::ulid(),
                'description' => $this->description,
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $moduleId = DB::table('modules')->where('name', $this->moduleName)->value('id');

        // 2️⃣ Insert module metadata if provided
        foreach ($this->metas as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            DB::table('module_metas')->updateOrInsert(
                ['ref_parent' => $moduleId, 'meta_key' => $key],
                [
                    'meta_value' => $value,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // 3️⃣ Create module-specific permissions
        foreach ($this->permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $this->guardName,
            ]);
        }

        // 4️⃣ Create super-admin role if it does not exist
        if (!empty($this->permissions)) {
            $superAdmin = Role::firstOrCreate([
                'name' => 'super-admin',
                'guard_name' => $this->guardName,
            ]);

            // Assign all module permissions to super-admin
            $superAdmin->givePermissionTo($this->permissions);

            if (app()->runningInConsole()) {
                echo $superAdmin->wasRecentlyCreated
                    ? "✅ Super-admin role created, module permissions assigned.\n"
                    : "✅ Super-admin role exists, module permissions assigned.\n";
            }
        }
    }
}