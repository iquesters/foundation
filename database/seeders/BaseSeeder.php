<?php

namespace Iquesters\Foundation\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * BaseModuleWithEntitiesSeeder
 *
 * A comprehensive abstract seeder for Laravel modules with entities.
 * Handles:
 *   - Module creation/updating
 *   - Module metadata
 *   - Module-specific permissions
 *   - Super-admin role creation & permission assignment
 *   - Entity definitions and metadata
 */
abstract class BaseSeeder extends Seeder
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
     * Entity definitions
     * Format: [
     *     'entity_name' => [
     *         'fields' => [...],
     *         'metas' => [...]
     *     ]
     * ]
     */
    protected array $entities = [];

    /**
     * Run the seeder
     */
    public function run(): void
    {
        // 1️⃣ Insert or update the module
        $this->seedModule();

        // 2️⃣ Get module ID for entity references
        $moduleId = DB::table('modules')
            ->where('name', $this->moduleName)
            ->value('id');

        // 3️⃣ Insert module metadata
        $this->seedModuleMetadata($moduleId);

        // 4️⃣ Create module-specific permissions
        $this->seedPermissions();

        // 5️⃣ Create super-admin role and assign permissions
        $this->seedSuperAdminRole();

        // 6️⃣ Seed entities for this module
        $this->seedEntities($moduleId);

        // 7️⃣ Hook for child seeders to add custom logic
        $this->seedCustom();
    }

    /**
     * Seed the module
     */
    protected function seedModule(): void
    {
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
    }

    /**
     * Seed module metadata
     */
    protected function seedModuleMetadata(int $moduleId): void
    {
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
    }

    /**
     * Seed permissions
     */
    protected function seedPermissions(): void
    {
        foreach ($this->permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $this->guardName,
            ]);
        }
    }

    /**
     * Seed super-admin role
     */
    protected function seedSuperAdminRole(): void
    {
        if (empty($this->permissions)) {
            return;
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => $this->guardName,
        ]);

        $superAdmin->givePermissionTo($this->permissions);

        if (app()->runningInConsole()) {
            $message = $superAdmin->wasRecentlyCreated
                ? "✅ Super-admin role created"
                : "✅ Super-admin role updated";
            echo "$message, module permissions assigned.\n";
        }
    }

    /**
     * Seed entities for this module
     */
    protected function seedEntities(int $moduleId): void
    {
        foreach ($this->entities as $entityName => $entityConfig) {
            $fields = $entityConfig['fields'] ?? [];
            $metaFields = $entityConfig['meta_fields'] ?? [];
            $metas = $entityConfig['metas'] ?? [];

            // Insert or update entity
            $entityData = [
                'uid' => (string) Str::ulid(),
                'ref_module' => $moduleId,
                'entity_name' => $entityName,
                'fields' => !empty($fields) ? json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'meta_fields' => !empty($metaFields) ? json_encode($metaFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ];

            DB::table('entities')->updateOrInsert(
                ['entity_name' => $entityName, 'ref_module' => $moduleId],
                $entityData
            );

            // Get entity ID
            $entityId = DB::table('entities')
                ->where('entity_name', $entityName)
                ->where('ref_module', $moduleId)
                ->value('id');

            // Insert entity metadata
            foreach ($metas as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                DB::table('entity_metas')->updateOrInsert(
                    ['ref_parent' => $entityId, 'meta_key' => $key],
                    [
                        'meta_value' => $value,
                        'status' => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            if (app()->runningInConsole()) {
                echo "✅ Entity '{$entityName}' seeded successfully.\n";
            }
        }
    }

    /**
     * Hook for child seeders to add custom logic
     * Override this method in child seeders if needed
     */
    protected function seedCustom(): void
    {
        // Override in child classes
    }
}