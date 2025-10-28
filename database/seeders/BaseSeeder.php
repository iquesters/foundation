<?php

namespace Iquesters\Foundation\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;
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
    protected string $guardName = "web";

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
        // 1️⃣ Auto-populate dynamic fields before any seeding
        $this->populateDynamicFields();

        // 2️⃣ Insert or update the module
        $this->seedModule();

        // 3️⃣ Get module ID for entity references
        $moduleId = DB::table('modules')
            ->where('name', $this->moduleName)
            ->value('id');

        // 4️⃣ Insert module metadata
        $this->seedModuleMetadata($moduleId);

        // 5️⃣ Create module-specific permissions
        $this->seedPermissions();

        // 6️⃣ Create super-admin role and assign permissions
        $this->seedSuperAdminRole();

        // 7️⃣ Seed entities for this module
        $this->seedEntities($moduleId);

        // 8️⃣ Seed Config for this module
        $this->seedConfig($moduleId);

        // 9️⃣ Hook for child seeders to add custom logic
        $this->seedCustom();
    }

    /**
     * Seed the module
     */
    final protected function seedModule(): void
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
    final protected function seedModuleMetadata(int $moduleId): void
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
    final protected function seedPermissions(): void
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
    final protected function seedSuperAdminRole(): void
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
    final protected function seedEntities(int $moduleId): void
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

    final protected function seedConfig(int $moduleId): void
    {
        
        /**
         * Ensure the 'config' root entry exists
        */
        $configRoot = DB::table('master_data')->where('key', 'config')->first();

        if (!$configRoot) {
            $configRootId = DB::table('master_data')->insertGetId([
                'key' => 'config',
                'value' => 'Application Configuration',
                'parent_id' => 0,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $configRootId = $configRoot->id;
        }

        /**
         * Insert module entry into master_data
         */
        $moduleMasterdataId = DB::table('master_data')->updateOrInsert(
            [
                'key' => "{$this->moduleName}-conf", // e.g., "UserManagement-conf"
            ],
            [
                'value' => $moduleId,
                'parent_id' => $configRootId,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        
        $moduleMasterdata = DB::table('master_data')
        ->where('key', "{$this->moduleName}-conf")
        ->first();
        
        $moduleMasterdataId = $moduleMasterdata->id;
    
        $moduleNamespacePart = str_replace(' ', '', ucwords(str_replace('-', ' ', $this->moduleName))); // PascalCase
        
        /**
         * Construct the main config class dynamically
         */
        $moduleConfClass = "Iquesters\\{$moduleNamespacePart}\\Config\\{$moduleNamespacePart}Conf";
        if (
            !class_exists($moduleConfClass) || 
            !is_subclass_of($moduleConfClass, \Iquesters\Foundation\Support\BaseConf::class)
        ) {
            throw new \RuntimeException("Main config class not found for module {$this->moduleName}");
        }

        /** @var \Iquesters\Foundation\Support\BaseConf $confInstance */
        $confInstance = new $moduleConfClass();

        $defaultConfig = $confInstance->getFlattenConfig(null, true, true);
        Log::debug($defaultConfig);

        /**
         * Insert each key into master_data_metas
         */
        foreach ($defaultConfig as $configItem) {
            $key = $configItem['key'];
            $value = $configItem['value'];
                        
            // Convert booleans to 'true'/'false' strings
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            
            // Handle arrays by converting to JSON
            if (is_array($value)) {
                $value = json_encode($value);
            }

            DB::table('master_data_metas')->updateOrInsert(
                [
                    'ref_parent' => $moduleMasterdataId,
                    'meta_key' => $key,
                ],
                [
                    'meta_value' => $value,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
    
    /**
     * Auto-populate dynamic fields in entities
     */
    final protected function populateDynamicFields(): void
    {
        foreach ($this->entities as $entityName => &$entityConfig) {
            if (isset($entityConfig['fields']) && empty($entityConfig['fields'])) {
                // If fields array exists but is empty, populate it dynamically
                $tableName = $entityConfig['table_name'] ?? $entityName;
                $entityConfig['fields'] = $this->getTableFields($tableName);
            }
        }
    }

    /**
     * Get table fields dynamically from database schema
     */
    protected function getTableFields(string $tableName): array
    {
        if (!Schema::hasTable($tableName)) {
            return [];
        }

        $fields = [];
        $columns = Schema::getColumns($tableName);

        foreach ($columns as $column) {
            $fields[$column['name']] = [
                'name' => $column['name'],
                'type' => $column['type_name'],
                'label' => ucwords(str_replace('_', ' ', $column['name'])),
                'required' => !$column['nullable'],
                'nullable' => $column['nullable'],
                'input_type' => $this->getInputType($column['name'], $column['type_name']),
                'maxlength' => $column['length'] ?? null,
                'default' => $column['default'] ?? null,
            ];
        }

        return $fields;
    }

    /**
     * Determine input type based on column name and type
     */
    protected function getInputType(string $columnName, string $columnType): string
    {
        // Map column names to input types
        $nameMap = [
            'email' => 'email',
            'password' => 'password',
            'phone' => 'tel',
            'url' => 'url',
            'image' => 'file',
            'file' => 'file',
            'color' => 'color',
        ];

        if (isset($nameMap[$columnName])) {
            return $nameMap[$columnName];
        }

        // Map by type
        $typeMap = [
            'text' => 'textarea',
            'boolean' => 'checkbox',
            'timestamp' => 'datetime-local',
            'datetime' => 'datetime-local',
            'date' => 'date',
            'integer' => 'number',
            'bigint' => 'number',
            'decimal' => 'number',
            'float' => 'number',
        ];

        return $typeMap[$columnType] ?? 'text';
    }
    
    /**
     * Hook for child seeders to add custom logic
     * Override this method in child seeders if needed
     */
    abstract protected function seedCustom(): void;
}