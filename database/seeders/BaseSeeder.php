<?php

namespace Iquesters\Foundation\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iquesters\Foundation\Jobs\BaseJob;
use Iquesters\Foundation\Models\Module;
use Iquesters\Foundation\Services\Navigation\NavigationSync;
use Iquesters\Foundation\System\Traits\Loggable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
    use Loggable;

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
        $this->seedTableSchemasFromSidebar($moduleId);
        // Sidebar items and their tabs → navigation_items (admin edits are kept).
        app(NavigationSync::class)
            ->syncModule(Module::findOrFail($moduleId));
        // 5️⃣ Create module-specific permissions
        $this->seedPermissions();

        // 6️⃣ Create super-admin role and assign permissions
        $this->seedSuperAdminRole();

        // 7️⃣ Seed entities for this module
        $this->seedEntities($moduleId);

        // 8️⃣ Seed Config for this module
        $this->seedConfig($moduleId);

        // Seed jobs dynamically
        $this->seedJobs();

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
                ? '✅ Super-admin role created'
                : '✅ Super-admin role updated';
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
            $tableName = $entityConfig['table_name'] ?? $this->generateTableName($entityName);
            $existingEntity = DB::table('entities')
                ->where('entity_name', $entityName)
                ->where('ref_module', $moduleId)
                ->first();
            $slug = $existingEntity->slug ?? $this->generateUniqueEntitySlug($entityName, $moduleId, $existingEntity->id ?? null);

            // Insert or update entity
            $entityData = [
                'uid' => $existingEntity->uid ?? (string) Str::ulid(),
                'ref_module' => $moduleId,
                'entity_name' => $entityName,
                'slug' => $slug,
                'fields' => ! empty($fields) ? json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'meta_fields' => ! empty($metaFields) ? json_encode($metaFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => $existingEntity->created_at ?? now(),
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

            DB::table('entity_metas')->updateOrInsert(
                ['ref_parent' => $entityId, 'meta_key' => 'table_name'],
                [
                    'meta_value' => $tableName,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

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

    protected function generateUniqueEntitySlug(string $entityName, int $moduleId, ?int $existingEntityId = null): string
    {
        $baseSlug = strtolower($entityName);
        $baseSlug = preg_replace('/[^a-z0-9]+/', '-', $baseSlug);
        $baseSlug = trim($baseSlug, '-');

        $slug = $baseSlug;
        $counter = 1;

        while (
            DB::table('entities')
                ->where('slug', $slug)
                ->when($existingEntityId, fn ($query) => $query->where('id', '!=', $existingEntityId))
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function generateTableName(string $entityName): string
    {
        return Str::snake(Str::pluralStudly($entityName));
    }

    /**
     * Settings are no longer seeded: defaults live in the module's settings
     * class (prepareDefault()) and only values an admin changes are stored
     * (module_settings, see BaseConf). So re-running a seeder can never
     * overwrite those changes. Kept so existing seeders calling it still work.
     */
    final protected function seedConfig(int $moduleId): void {}

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
        if (! Schema::hasTable($tableName)) {
            return [];
        }

        $fields = [];
        $columns = Schema::getColumns($tableName);

        foreach ($columns as $column) {
            $fields[$column['name']] = [
                'name' => $column['name'],
                'type' => $column['type_name'],
                'label' => ucwords(str_replace('_', ' ', $column['name'])),
                // A NOT NULL column with a default (e.g. status, source) can be left out.
                'required' => ! $column['nullable'] && $column['default'] === null && ! $column['auto_increment'],
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
     * Seed table schemas declared inside sidebar menu items
     */
    final protected function seedTableSchemasFromSidebar(int $moduleId): void
    {
        // Get the current sidebar menu meta
        $sidebarMeta = DB::table('module_metas')
            ->where('ref_parent', $moduleId)
            ->where('meta_key', 'module_sidebar_menu')
            ->first();

        $sidebarMenu = $sidebarMeta
            ? json_decode($sidebarMeta->meta_value, true)
            : ($this->metas['module_sidebar_menu'] ?? []);

        foreach ($sidebarMenu as &$menuItem) {

            // Check if this menu item declares a table schema
            if (empty($menuItem['table_schema'])) {
                continue;
            }

            $schemaDef = $menuItem['table_schema'];

            // Check if schema already exists
            $existing = DB::table('table_schemas')
                ->where('slug', $schemaDef['slug'])
                ->first();

            if (! $existing) {
                // Insert new table schema
                DB::table('table_schemas')->insert([
                    'uid' => (string) Str::ulid(),
                    'slug' => $schemaDef['slug'],
                    'name' => $schemaDef['name'],
                    'description' => $schemaDef['description'] ?? null,
                    'schema' => json_encode($schemaDef['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'status' => 'active',
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $schemaUid = DB::table('table_schemas')
                    ->where('slug', $schemaDef['slug'])
                    ->value('uid');
            } else {
                // Use existing UID
                $schemaUid = $existing->uid;
            }

            // Bind UID to sidebar menu item
            $menuItem['default-table-schema-uid'] = $schemaUid;

            // Optional: remove schema payload to keep sidebar clean
            unset($menuItem['table_schema']);
        }

        // Save updated sidebar menu back to module_meta
        DB::table('module_metas')->updateOrInsert(
            [
                'ref_parent' => $moduleId,
                'meta_key' => 'module_sidebar_menu',
            ],
            [
                'meta_value' => json_encode($sidebarMenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * Automatically seed jobs from the Jobs directory
     *
     * This method scans the module's Jobs directory and registers all concrete
     * classes that extend BaseJob into the queues table with default metadata.
     *
     * Directory Detection Strategy:
     * - Tries multiple common module structures
     * - Uses the first existing Jobs directory found
     *
     * Supports structures like:
     * - database/seeders → src/Jobs
     *
     * Skips:
     * - Abstract classes
     * - Classes that don't extend BaseJob
     * - Files with invalid PHP syntax
     */
    final protected function seedJobs(): void
    {
        $now = now();

        try {
            // Determine module Jobs directory
            $reflection = new \ReflectionClass(static::class);
            $seederFile = $reflection->getFileName();

            // Only support database/seeders → src/Jobs
            $moduleJobsDir = dirname($seederFile, 3).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Jobs';

            if (! is_dir($moduleJobsDir)) {
                if (app()->runningInConsole()) {
                    echo "ℹ️  No Jobs directory found\n";
                }

                return;
            }

            // Scan for all PHP files in Jobs directory
            $jobFiles = $this->scanDirectoryRecursively($moduleJobsDir);

            if (empty($jobFiles)) {
                return;
            }

            // Process each file and collect valid jobs
            $allJobs = [];

            foreach ($jobFiles as $file) {
                // Extract fully qualified class name
                $class = $this->getClassFullNameFromFile($file);

                if (! $class || ! class_exists($class)) {
                    continue;
                }

                try {
                    $refClass = new \ReflectionClass($class);

                    // Only include concrete classes that extend BaseJob
                    if ($refClass->isSubclassOf(BaseJob::class) && ! $refClass->isAbstract()) {
                        $allJobs[] = $class;
                    }
                } catch (\ReflectionException $e) {
                    // Skip files that cause reflection errors
                    static::logWarning('Job class reflection failed', [
                        'class' => $class,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            // Exit if no valid jobs found
            if (empty($allJobs)) {
                return;
            }

            // Insert/update jobs in database
            foreach ($allJobs as $jobClass) {
                $queueName = class_basename($jobClass);

                // Insert or update queue record
                DB::table('queues')->updateOrInsert(
                    ['name' => $queueName],
                    [
                        'uid' => (string) Str::ulid(),
                        'description' => ucfirst($queueName),
                        'status' => 'active',
                        'created_by' => null,
                        'updated_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                $queueId = DB::table('queues')->where('name', $queueName)->value('id');

                // Insert or update queue metadata with default values
                $queueMetas = [
                    'max_workers' => 2,      // Maximum concurrent workers
                    'max_tries' => 3,      // Maximum retry attempts
                    'timeout' => 120,    // Job timeout in seconds
                    'sleep' => 3,      // Sleep time between jobs in seconds
                    'memory' => 128,    // Memory limit in MB
                ];

                foreach ($queueMetas as $key => $value) {
                    DB::table('queue_metas')->updateOrInsert(
                        [
                            'ref_parent' => $queueId,
                            'meta_key' => $key,
                        ],
                        [
                            'meta_value' => $value,
                            'status' => 'active',
                            'created_by' => null,
                            'updated_by' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }

                // Output to console
                if (app()->runningInConsole()) {
                    echo "✅ Queue seeded: $queueName\n";
                }
            }

        } catch (\Exception $e) {
            // Log critical errors but don't halt seeding
            static::logError('Job seeding failed', [
                'module' => $this->moduleName ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            if (app()->runningInConsole()) {
                echo '⚠️  Job seeding error: '.$e->getMessage()."\n";
            }
        }
    }

    /**
     * Recursively scan directory for PHP files
     *
     * @param  string  $dir  Directory path to scan
     * @return array Array of file paths
     */
    protected function scanDirectoryRecursively(string $dir): array
    {
        $files = [];

        if (! is_readable($dir)) {
            return $files;
        }

        $items = @scandir($dir);

        if ($items === false) {
            return $files;
        }

        foreach ($items as $item) {
            // Skip hidden directories
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                // Recursively scan subdirectories
                $files = array_merge($files, $this->scanDirectoryRecursively($path));
            } elseif (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                // Collect PHP files
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Extract fully qualified class name from PHP file
     *
     * Parses the file content to extract namespace and class name
     * Handles abstract and final class declarations
     *
     * @param  string  $file  File path
     * @return string|null Fully qualified class name or null if not found
     */
    protected function getClassFullNameFromFile(string $file): ?string
    {
        if (! is_readable($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        $namespace = '';
        $class = '';

        // Extract namespace (e.g., "namespace App\Jobs\MessageJobs;")
        if (preg_match('/^\s*namespace\s+([a-zA-Z0-9_\\\\]+)\s*;/m', $contents, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract class name (handles: class, abstract class, final class)
        if (preg_match('/^\s*(?:abstract\s+)?(?:final\s+)?class\s+([a-zA-Z0-9_]+)/m', $contents, $matches)) {
            $class = trim($matches[1]);
        }

        // Return fully qualified class name
        if ($namespace && $class) {
            return $namespace.'\\'.$class;
        }

        return $class ?: null;
    }

    /**
     * Hook for child seeders to add custom logic
     * Override this method in child seeders if needed
     */
    abstract protected function seedCustom(): void;
}
