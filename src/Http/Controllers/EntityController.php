<?php

namespace Iquesters\Foundation\Http\Controllers;

use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\Models\Module;
use Iquesters\Foundation\Services\Schema\EntitySchemaService;
use Iquesters\Foundation\Support\EntityPermission;
use Iquesters\Foundation\Support\EntityTableName;
use Iquesters\Foundation\System\Traits\Loggable;

class EntityController extends Controller
{
    use Loggable;

    public function __construct(
        private readonly EntitySchemaService $schemas
    ) {}

    private const SUPPORTED_PRIMARY_FIELD_TYPES = [
        'string',
        'text',
        'longtext',
        'integer',
        'decimal',
        'boolean',
        'date',
        'datetime',
        'time',
    ];

    private const SUPPORTED_META_FIELD_TYPES = [
        'string',
        'text',
        'longtext',
        'integer',
        'decimal',
        'boolean',
        'date',
        'datetime',
        'time',
    ];

    private const SUPPORTED_INPUT_TYPES = [
        'text',
        'hidden',
        'textarea',
        'number',
        'email',
        'date',
        'datetime-local',
        'time',
        'checkbox',
        'select',
    ];

    public function index()
    {
        try {
            static::logInfo('Fetching all entity');

            $entities = Entity::with('metas')
                ->get();
            static::logInfo('Displaying entities', ['entities' => $entities->toArray()]);

            return view('foundation::entity.index', [
                'entities' => $entities,
            ]);
        } catch (Exception $e) {
            static::logError('Error fetching entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'An error occurred while fetching entity.');
        }
    }

    public function create()
    {
        try {
            static::logInfo('Creating new entity');
            $modules = Module::where('status', 'active')->get();

            return view('foundation::entity.create-edit', [
                'entity' => null,
                'isCreating' => true,
                'modules' => $modules,
            ]);
        } catch (Exception $e) {
            static::logError('Error displaying create form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'An error occurred while loading the create form.');
        }
    }

    public function edit($uid)
    {
        try {
            static::logInfo('Editing entity', ['uid' => $uid]);

            $entity = Entity::where('uid', $uid)->firstOrFail();
            $modules = Module::where('status', 'active')->get();

            return view('foundation::entity.create-edit', [
                'entity' => $entity,
                'isCreating' => false,
                'modules' => $modules,
            ]);
        } catch (Exception $e) {
            static::logError('Error displaying edit form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'uid' => $uid,
            ]);

            return redirect()
                ->back()
                ->with('error', 'An error occurred while loading the edit form.');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'entity_name' => 'required|string|max:255|unique:entities,entity_name',
                'ref_module' => 'required|exists:modules,id',
                'desc' => 'nullable|string',
                'fields' => 'required|json',
                'meta_fields' => 'nullable|json',
            ]);

            $tableName = EntityTableName::fromEntityName($validated['entity_name']);
            $slug = $this->generateUniqueSlug($validated['entity_name']);
            $processedCustomFields = $this->processCustomFields($validated['fields']);
            $allFields = $this->mergeSystemAndCustomFields($processedCustomFields);
            $metaFieldsData = $this->decodeMetaFields($validated['meta_fields'] ?? null);

            // Create entity
            $entity = new Entity;
            $entity->uid = Str::ulid();
            $entity->ref_module = $validated['ref_module'];
            $entity->entity_name = $validated['entity_name'];
            $entity->slug = $slug; // ✅ explicitly assigned
            $entity->desc = $validated['desc'] ?? null;
            $entity->fields = $allFields;
            $entity->meta_fields = $metaFieldsData;
            $entity->status = 'active';
            $entity->created_by = auth()->id();
            $entity->save();

            // Save metadata and generate schemas
            $this->schemas->setMeta($entity, 'table_name', $tableName);
            ['form_schema_uid' => $formSchemaUid, 'table_schema_uid' => $tableSchemaUid] = $this->schemas->sync($entity, $processedCustomFields, $metaFieldsData);

            static::logInfo('Entity created with schemas', [
                'entity' => $entity->toArray(),
                'table_name' => $tableName,
                'form_schema_uid' => $formSchemaUid,
                'table_schema_uid' => $tableSchemaUid,
            ]);

            return redirect()
                ->route('entities.show', $entity->uid)
                ->with('success', 'Entity created successfully with form and table schemas.');

        } catch (Exception $e) {
            static::logError('Error creating entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $uid)
    {
        try {
            $entity = Entity::where('uid', $uid)->firstOrFail();

            $isPublished = $entity->status === 'published';

            $validated = $request->validate([
                'entity_name' => 'required|string|max:255|unique:entities,entity_name,'.$entity->id,
                'ref_module' => 'required|exists:modules,id',
                'desc' => 'nullable|string',
                'fields' => 'required|json',
                'meta_fields' => 'nullable|json',
            ]);

            // ✅ Primary fields: LOCK after publish
            if ($isPublished) {
                $allFields = $entity->fields;
            } else {
                $processedCustomFields = $this->processCustomFields($validated['fields']);
                $allFields = $this->mergeSystemAndCustomFields($processedCustomFields);
            }

            // Meta fields are always editable
            $metaFieldsData = $this->decodeMetaFields($validated['meta_fields'] ?? null);

            // ❗ Table name MUST NOT change after publish
            if (! $isPublished) {
                $tableName = EntityTableName::fromEntityName($validated['entity_name']);
                $this->schemas->setMeta($entity, 'table_name', $tableName);
            }

            // Update entity
            $entity->update([
                'ref_module' => $validated['ref_module'],
                'entity_name' => $validated['entity_name'],
                'desc' => $validated['desc'] ?? null,
                'fields' => $allFields,
                'meta_fields' => $metaFieldsData,
                'updated_by' => auth()->id(),
            ]);

            $customFieldsForSchema = $isPublished
                ? $this->extractCustomFieldsFromEntityFields($allFields)
                : ($processedCustomFields ?? []);

            $this->schemas->syncFormSchema($entity, $customFieldsForSchema, $metaFieldsData);

            static::logInfo('Entity updated', [
                'entity_uid' => $entity->uid,
                'published' => $isPublished,
            ]);

            return redirect()
                ->route('entities.show', $entity->uid)
                ->with('success', 'Entity updated successfully.');

        } catch (Exception $e) {
            static::logError('Error updating entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Generate unique slug for entity
     * price, price-1, price-2 ...
     */
    private function generateUniqueSlug(string $entityName): string
    {
        // Convert entity name to table-safe slug
        $baseSlug = strtolower($entityName);
        $baseSlug = preg_replace('/[^a-z0-9]+/', '-', $baseSlug);
        $baseSlug = trim($baseSlug, '-');

        $slug = $baseSlug;
        $counter = 1;

        while (
            Entity::where('slug', $slug)->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get system default fields
     */
    private function getSystemFields()
    {
        return [
            'id' => [
                'name' => 'id',
                'type' => 'bigint',
                'label' => 'Id',
                'required' => true,
                'nullable' => false,
                'input_type' => 'number',
                'maxlength' => null,
                'default' => null,
            ],
            'uid' => [
                'name' => 'uid',
                'type' => 'char',
                'label' => 'Uid',
                'required' => true,
                'nullable' => false,
                'input_type' => 'text',
                'maxlength' => null,
                'default' => null,
            ],
            'status' => [
                'name' => 'status',
                'type' => 'varchar',
                'label' => 'Status',
                'required' => true,
                'nullable' => false,
                'input_type' => 'text',
                'maxlength' => null,
                'default' => "'unknown'",
            ],
            'created_by' => [
                'name' => 'created_by',
                'type' => 'bigint',
                'label' => 'Created By',
                'required' => false,
                'nullable' => true,
                'input_type' => 'number',
                'maxlength' => null,
                'default' => 'NULL',
            ],
            'created_at' => [
                'name' => 'created_at',
                'type' => 'timestamp',
                'label' => 'Created At',
                'required' => false,
                'nullable' => true,
                'input_type' => 'datetime-local',
                'maxlength' => null,
                'default' => 'NULL',
            ],
            'updated_by' => [
                'name' => 'updated_by',
                'type' => 'bigint',
                'label' => 'Updated By',
                'required' => false,
                'nullable' => true,
                'input_type' => 'number',
                'maxlength' => null,
                'default' => 'NULL',
            ],
            'updated_at' => [
                'name' => 'updated_at',
                'type' => 'timestamp',
                'label' => 'Updated At',
                'required' => false,
                'nullable' => true,
                'input_type' => 'datetime-local',
                'maxlength' => null,
                'default' => 'NULL',
            ],
            'deleted_by' => [
                'name' => 'deleted_by',
                'type' => 'bigint',
                'label' => 'Deleted By',
                'required' => false,
                'nullable' => true,
                'input_type' => 'number',
                'maxlength' => null,
                'default' => 'NULL',
            ],
            'deleted_at' => [
                'name' => 'deleted_at',
                'type' => 'timestamp',
                'label' => 'Deleted At',
                'required' => false,
                'nullable' => true,
                'input_type' => 'datetime-local',
                'maxlength' => null,
                'default' => 'NULL',
            ],
        ];
    }

    /**
     * Get type mapping for fields
     */
    private function getTypeMapping()
    {
        return [
            'string' => 'varchar',
            'text' => 'text',
            'longtext' => 'longtext',
            'integer' => 'bigint',
            'decimal' => 'decimal',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'timestamp',
            'time' => 'time',
        ];
    }

    /**
     * Process custom fields from JSON
     */
    private function processCustomFields($fieldsJson)
    {
        $customFields = json_decode($fieldsJson, true);
        $typeMapping = $this->getTypeMapping();
        $processedCustomFields = [];

        if (is_array($customFields)) {
            foreach ($customFields as $field) {
                $fieldName = $field['name'];
                $fieldType = $field['type'];

                if (! in_array($fieldType, self::SUPPORTED_PRIMARY_FIELD_TYPES, true)) {
                    throw new Exception("Field type '{$fieldType}' is not supported for primary fields.");
                }

                if (! in_array($field['input_type'], self::SUPPORTED_INPUT_TYPES, true)) {
                    throw new Exception("Input type '{$field['input_type']}' is not supported.");
                }

                $processedCustomFields[$fieldName] = [
                    'name' => $fieldName,
                    'type' => $typeMapping[$fieldType] ?? $fieldType,
                    'label' => $field['label'],
                    'required' => $field['required'] ?? false,
                    'nullable' => $field['nullable'] ?? true,
                    'input_type' => $field['input_type'],
                    'maxlength' => $field['maxlength'] ?? null,
                    'size' => $field['size'] ?? null,
                    'gridSize' => $field['gridSize'] ?? null,
                    'default' => $this->normalizeFieldDefault($field['default'] ?? null, $fieldType),
                    // Validation keys enforced by the generic entity API (EntityValidator).
                    'unique' => (bool) ($field['unique'] ?? false),
                    'options' => is_array($field['options'] ?? null) ? $field['options'] : null,
                    'min' => isset($field['min']) && is_numeric($field['min']) ? $field['min'] + 0 : null,
                    'max' => isset($field['max']) && is_numeric($field['max']) ? $field['max'] + 0 : null,
                ];
            }
        }

        return $processedCustomFields;
    }

    /**
     * Merge system fields with custom fields
     */
    private function mergeSystemAndCustomFields($customFields)
    {
        return array_merge($this->getSystemFields(), $customFields);
    }

    private function normalizeFieldDefault(mixed $default, string $fieldType): mixed
    {
        if ($default === null) {
            return null;
        }

        if (is_string($default)) {
            $default = trim($default);

            if ($default === '' || Str::lower($default) === 'null') {
                return null;
            }
        }

        return match ($fieldType) {
            'integer' => (int) $default,
            'decimal' => (float) $default,
            'boolean' => filter_var($default, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            default => $default,
        };
    }

    /**
     * Decode meta fields from JSON
     */
    private function decodeMetaFields($metaFieldsJson)
    {
        $metaFields = $metaFieldsJson ? json_decode($metaFieldsJson, true) : [];

        if (! is_array($metaFields)) {
            return [];
        }

        foreach ($metaFields as $field) {
            $fieldType = $field['type'] ?? null;
            $inputType = $field['input_type'] ?? null;

            if ($fieldType && ! in_array($fieldType, self::SUPPORTED_META_FIELD_TYPES, true)) {
                throw new Exception("Meta field type '{$fieldType}' is not supported.");
            }

            if ($inputType && ! in_array($inputType, self::SUPPORTED_INPUT_TYPES, true)) {
                throw new Exception("Meta input type '{$inputType}' is not supported.");
            }
        }

        return $metaFields;
    }

    private function extractCustomFieldsFromEntityFields(array $fields): array
    {
        $systemFieldNames = array_keys($this->getSystemFields());

        return array_filter($fields, function ($field) use ($systemFieldNames) {
            return ! in_array($field['name'] ?? null, $systemFieldNames, true);
        });
    }

    public function show($entityUid)
    {
        try {
            $entity = Entity::with('metas')
                ->where('uid', $entityUid)
                ->first();

            if (! $entity) {
                return redirect()
                    ->back()
                    ->with('error', 'Entity not found.');
            }

            return view('foundation::entity.show', [
                'entity' => $entity,
            ]);

        } catch (\Throwable $th) {
            static::logError('Error fetching entity', [
                'entity_uid' => $entityUid,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', $th->getMessage());
        }
    }

    public function generateFormSchemaFromEntity(string $entityUid)
    {
        try {
            $entity = Entity::with('metas')->where('uid', $entityUid)->firstOrFail();
            $customFields = $this->extractCustomFieldsFromEntityFields($entity->fields ?? []);
            $metaFields = is_array($entity->meta_fields) ? $entity->meta_fields : [];

            $this->schemas->syncFormSchema($entity, $customFields, $metaFields);

            return redirect()
                ->route('entities.show', $entity->uid)
                ->with('success', 'Form schema generated successfully.');
        } catch (Exception $e) {
            static::logError('Error generating form schema from entity', [
                'entity_uid' => $entityUid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function generateTableSchemaFromEntity(string $entityUid)
    {
        try {
            $entity = Entity::with('metas')->where('uid', $entityUid)->firstOrFail();
            $customFields = $this->extractCustomFieldsFromEntityFields($entity->fields ?? []);
            $metaFields = is_array($entity->meta_fields) ? $entity->meta_fields : [];

            $this->schemas->sync($entity, $customFields, $metaFields);

            return redirect()
                ->route('entities.show', $entity->uid)
                ->with('success', 'Table schema generated successfully.');
        } catch (Exception $e) {
            static::logError('Error generating table schema from entity', [
                'entity_uid' => $entityUid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function destroy($entityUid)
    {
        try {
            $entity = Entity::where('uid', $entityUid)->firstOrFail();

            $entity->update([
                'status' => 'deleted',
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

            static::logInfo('Entity marked as deleted', [
                'entity' => $entity->toArray(),
            ]);

            return redirect()
                ->route('entities.index')
                ->with('success', 'Entity deleted successfully.');

        } catch (Exception $e) {
            static::logError('Error deleting entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function publish($entityUid)
    {
        try {
            static::logDebug('Publishing entity', [
                'entityUid' => $entityUid,
            ]);
            $entity = Entity::where('uid', $entityUid)->firstOrFail();

            // Check if entity is active
            if ($entity->status !== 'active') {
                return redirect()
                    ->back()
                    ->with('error', 'Only active entities can be published. Current status: '.$entity->status);
            }

            // Get table name from entity meta
            $tableMeta = $entity->metas()->where('meta_key', 'table_name')->first();

            if (! $tableMeta) {
                return redirect()
                    ->back()
                    ->with('error', 'Table name not found in entity meta.');
            }

            $baseTableName = $tableMeta->meta_value;

            // Pluralize table name if not already plural
            $tableName = EntityTableName::plural($baseTableName);
            $metaTableName = $this->getMetaTableName($baseTableName);

            // Check if tables already exist
            if (Schema::hasTable($tableName)) {
                return redirect()
                    ->back()
                    ->with('error', "Table '{$tableName}' already exists.");
            }

            // Get fields from entity
            $fields = is_string($entity->fields)
                ? json_decode($entity->fields, true)
                : $entity->fields;

            // Get meta fields from entity
            $metaFields = is_string($entity->meta_fields)
                ? json_decode($entity->meta_fields, true)
                : $entity->meta_fields;

            // Separate and order fields
            $orderedFields = $this->orderFields($fields);

            // Create main entity table
            Schema::create($tableName, function (Blueprint $table) use ($orderedFields) {
                foreach ($orderedFields as $field) {
                    $this->addColumnToTable($table, $field);
                }
            });

            static::logInfo("Table '{$tableName}' created successfully", [
                'fields' => $orderedFields,
            ]);

            // Create meta table
            Schema::create($metaTableName, function (Blueprint $table) use ($tableName) {
                $table->id();

                $table->unsignedBigInteger('ref_parent')->index();
                $table->string('meta_key')->index();
                $table->longText('meta_value')->nullable();
                $table->string('status')->default('unknown');

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

                // DB-level timestamps (AUTO)
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->timestamp('deleted_at')->nullable();

                $table->unique(['ref_parent', 'meta_key']);

                $table->foreign('ref_parent')
                    ->references('id')
                    ->on($tableName)
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            });

            static::logInfo("Meta table '{$metaTableName}' created successfully");

            // Per-entity permissions for the generic entity API.
            EntityPermission::ensureFor($tableName);

            // Update entity status to published
            $entity->update([
                'status' => 'published',
                'updated_by' => auth()->id(),
            ]);

            // Save published status in entity meta
            $entity->metas()->updateOrCreate(
                ['meta_key' => 'published_at'],
                [
                    'meta_value' => now()->toDateTimeString(),
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]
            );

            // Update table names in entity meta
            $entity->metas()->updateOrCreate(
                ['meta_key' => 'published_table_name'],
                [
                    'meta_value' => $tableName,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]
            );

            $entity->metas()->updateOrCreate(
                ['meta_key' => 'published_meta_table_name'],
                [
                    'meta_value' => $metaTableName,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]
            );

            return redirect()
                ->back()
                ->with('success', "Entity published successfully. Tables '{$tableName}' and '{$metaTableName}' created.");

        } catch (Exception $e) {
            static::logError('Error publishing entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Error: '.$e->getMessage());
        }
    }

    /**
     * Get meta table name
     */
    private function getMetaTableName($baseTableName)
    {
        // If base name ends with 's', check if it's already plural
        if (substr($baseTableName, -1) === 's') {
            // Likely already plural (e.g., 'users')
            return $baseTableName.'_metas';
        } elseif (substr($baseTableName, -1) === 'y') {
            // category -> category_metas (keep singular for meta table relationship)
            return $baseTableName.'_metas';
        } else {
            return $baseTableName.'_metas';
        }
    }

    /**
     * Order fields: id, uid, custom fields, status, created_by, updated_by, deleted_by, timestamps
     */
    private function orderFields($fields)
    {
        $systemFieldOrder = [
            'id' => 1,
            'uid' => 2,
            'status' => 998,
            'created_by' => 999,
            'updated_by' => 1000,
            'deleted_by' => 1001,
            'created_at' => 1002,
            'updated_at' => 1003,
            'deleted_at' => 1004,
        ];

        $orderedFields = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            if (isset($systemFieldOrder[$name])) {
                $orderedFields[$systemFieldOrder[$name]] = $field;
            } else {
                // Custom fields get priority between uid (2) and status (998)
                // Start from 100 to leave room
                $orderedFields[100 + count($orderedFields)] = $field;
            }
        }

        ksort($orderedFields);

        return array_values($orderedFields);
    }

    /**
     * Add column to table based on field definition
     */
    private function addColumnToTable(Blueprint $table, array $field)
    {
        $name = $field['name'];
        $type = $field['type'];
        $nullable = $field['nullable'] ?? true;
        $default = $field['default'] ?? null;

        // Handle different column types
        switch ($type) {
            case 'bigint':
                if ($name === 'id') {
                    $column = $table->id();
                } elseif (in_array($name, ['created_by', 'updated_by', 'deleted_by'], true)) {
                    // Nullable FK to users (no fake "0" user).
                    return $table->foreignId($name)->nullable()->constrained('users')->nullOnDelete();
                } else {
                    $column = $table->bigInteger($name);
                }
                break;

            case 'char':
                if ($name === 'uid') {
                    $column = $table->ulid('uid')->unique();
                } else {
                    $column = $table->char($name, 255);
                }
                break;

            case 'varchar':
                $column = $table->string($name, 255);
                break;

            case 'text':
                $column = $table->text($name);
                break;

            case 'longtext':
                $column = $table->longText($name);
                break;

            case 'decimal':
                $column = $table->decimal($name, 10, 2);
                break;

            case 'boolean':
                $column = $table->boolean($name);
                break;

            case 'date':
                $column = $table->date($name);
                break;

            case 'time':
                $column = $table->time($name);
                break;

            case 'timestamp':
                if (in_array($name, ['created_at', 'updated_at'])) {
                    $column = $table->timestamp($name)
                        ->useCurrent();

                    if ($name === 'updated_at') {
                        $column->useCurrentOnUpdate();
                    }
                } else {
                    $column = $table->timestamp($name)->nullable();
                }
                break;

            default:
                $column = $table->string($name);
                break;
        }

        // Apply nullable
        if (
            $nullable &&
            ! in_array($name, ['id', 'uid', 'created_by', 'updated_by'])
        ) {
            $column->nullable();
        }

        // Apply default value
        if ($default !== null && $name !== 'id' && $name !== 'uid') {
            $defaultValue = is_string($default) ? trim($default, "'\"") : $default;

            if (is_string($defaultValue) && Str::lower($defaultValue) === 'null') {
                $column->nullable();
            } elseif ($type === 'boolean') {
                $column->default($defaultValue === 'true' || $defaultValue === '1');
            } elseif (in_array($type, ['bigint', 'decimal'])) {
                $column->default((float) $defaultValue);
            } else {
                $column->default($defaultValue);
            }
        }

        // Add indexes for commonly queried fields
        if (in_array($name, ['status', 'deleted_at'])) {
            $column->index();
        }

        // Fields marked unique get a DB-level unique index as well as API validation.
        if (! empty($field['unique']) && ! in_array($name, ['id', 'uid'], true)) {
            $column->unique();
        }
    }
}
