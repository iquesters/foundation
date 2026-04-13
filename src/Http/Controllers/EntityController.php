<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Iquesters\Foundation\Services\FormSchemaGenerator;
use Iquesters\Foundation\Services\TableSchemaGenerator;
use Iquesters\UserInterface\Models\FormSchema;
use Iquesters\UserInterface\Models\TableSchema;
use Illuminate\Database\Schema\Blueprint;
use Iquesters\Foundation\Models\Module;
use Illuminate\Support\Facades\Schema;
use Exception;

class EntityController extends Controller
{
    public function __construct(
        private readonly FormSchemaGenerator $formSchemaGenerator,
        private readonly TableSchemaGenerator $tableSchemaGenerator
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
            Log::info('Fetching all entity');

            $entities = Entity::with('metas')
                ->get();
            Log::info('Displaying entities', ['entities' => $entities->toArray()]);

            return view('foundation::entity.index', [
                'entities' => $entities
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()
                ->back()
                ->with('error', 'An error occurred while fetching entity.');
        }
    }
    
    public function create()
    {
        try {
            Log::info('Creating new entity');
            $modules = Module::where('status', 'active')->get();
            
            return view('foundation::entity.create-edit', [
                'entity' => null,
                'isCreating' => true,
                'modules'    => $modules,
            ]);
        } catch (Exception $e) {
            Log::error('Error displaying create form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()
                ->back()
                ->with('error', 'An error occurred while loading the create form.');
        }
    }

    public function edit($uid)
    {
        try {
            Log::info('Editing entity', ['uid' => $uid]);

            $entity = Entity::where('uid', $uid)->firstOrFail();
            $modules = Module::where('status', 'active')->get();

            return view('foundation::entity.create-edit', [
                'entity' => $entity,
                'isCreating' => false,
                'modules'    => $modules,
            ]);
        } catch (Exception $e) {
            Log::error('Error displaying edit form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'uid' => $uid
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

            $tableName = $this->generateTableName($validated['entity_name']);
            $slug = $this->generateUniqueSlug($validated['entity_name']);
            $processedCustomFields = $this->processCustomFields($validated['fields']);
            $allFields = $this->mergeSystemAndCustomFields($processedCustomFields);
            $metaFieldsData = $this->decodeMetaFields($validated['meta_fields'] ?? null);

            // Create entity
            $entity = new Entity();
            $entity->uid = Str::ulid();
            $entity->ref_module = $validated['ref_module'];
            $entity->entity_name = $validated['entity_name'];
            $entity->slug = $slug; // ✅ explicitly assigned
            $entity->desc = $validated['desc'] ?? null;
            $entity->fields = $allFields;
            $entity->meta_fields = $metaFieldsData;
            $entity->status = 'active';
            $entity->created_by = auth()->id() ?? 0;
            $entity->save();

            // Save metadata and generate schemas
            $this->saveEntityMeta($entity, 'table_name', $tableName);
            $formSchemaUid = $this->createFormSchema($entity, $slug, $processedCustomFields, $metaFieldsData);
            $this->saveEntityMeta($entity, 'form_schema_uid', $formSchemaUid);
            $tableSchemaUid = $this->createTableSchema($entity, $slug, $processedCustomFields, $formSchemaUid);
            $this->saveEntityMeta($entity, 'table_schema_uid', $tableSchemaUid);

            Log::info('Entity created with schemas', [
                'entity' => $entity->toArray(),
                'table_name' => $tableName,
                'form_schema_uid' => $formSchemaUid,
                'table_schema_uid' => $tableSchemaUid
            ]);

            return redirect()
                ->route('entities.show', $entity->uid)
                ->with('success', 'Entity created successfully with form and table schemas.');
                
        } catch (Exception $e) {
            Log::error('Error creating entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
                'entity_name' => 'required|string|max:255|unique:entities,entity_name,' . $entity->id,
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
            if (!$isPublished) {
                $tableName = $this->generateTableName($validated['entity_name']);
                $this->saveEntityMeta($entity, 'table_name', $tableName, false);
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

            $this->ensureGeneratedFormSchema($entity, $customFieldsForSchema, $metaFieldsData);

            Log::info('Entity updated', [
                'entity_uid' => $entity->uid,
                'published' => $isPublished
            ]);

            return redirect()
                ->route('entities.show', $entity->uid)
                ->with('success', 'Entity updated successfully.');

        } catch (Exception $e) {
            Log::error('Error updating entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate table name from entity name
     */
    private function generateTableName($entityName)
    {
        $tableName = strtolower($entityName);
        $tableName = preg_replace('/[^a-z0-9]+/', '_', $tableName);
        return trim($tableName, '_');
    }

    /**
     * Get system default fields
     */
    private function getSystemFields()
    {
        return [
            "id" => [
                "name" => "id",
                "type" => "bigint",
                "label" => "Id",
                "required" => true,
                "nullable" => false,
                "input_type" => "number",
                "maxlength" => null,
                "default" => null
            ],
            "uid" => [
                "name" => "uid",
                "type" => "char",
                "label" => "Uid",
                "required" => true,
                "nullable" => false,
                "input_type" => "text",
                "maxlength" => null,
                "default" => null
            ],
            "status" => [
                "name" => "status",
                "type" => "varchar",
                "label" => "Status",
                "required" => true,
                "nullable" => false,
                "input_type" => "text",
                "maxlength" => null,
                "default" => "'unknown'"
            ],
            "created_by" => [
                "name" => "created_by",
                "type" => "bigint",
                "label" => "Created By",
                "required" => false,
                "nullable" => true,
                "input_type" => "number",
                "maxlength" => null,
                "default" => "NULL"
            ],
            "created_at" => [
                "name" => "created_at",
                "type" => "timestamp",
                "label" => "Created At",
                "required" => false,
                "nullable" => true,
                "input_type" => "datetime-local",
                "maxlength" => null,
                "default" => "NULL"
            ],
            "updated_by" => [
                "name" => "updated_by",
                "type" => "bigint",
                "label" => "Updated By",
                "required" => false,
                "nullable" => true,
                "input_type" => "number",
                "maxlength" => null,
                "default" => "NULL"
            ],
            "updated_at" => [
                "name" => "updated_at",
                "type" => "timestamp",
                "label" => "Updated At",
                "required" => false,
                "nullable" => true,
                "input_type" => "datetime-local",
                "maxlength" => null,
                "default" => "NULL"
            ],
            "deleted_by" => [
                "name" => "deleted_by",
                "type" => "bigint",
                "label" => "Deleted By",
                "required" => false,
                "nullable" => true,
                "input_type" => "number",
                "maxlength" => null,
                "default" => "NULL"
            ],
            "deleted_at" => [
                "name" => "deleted_at",
                "type" => "timestamp",
                "label" => "Deleted At",
                "required" => false,
                "nullable" => true,
                "input_type" => "datetime-local",
                "maxlength" => null,
                "default" => "NULL"
            ]
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
                    "name" => $fieldName,
                    "type" => $typeMapping[$fieldType] ?? $fieldType,
                    "label" => $field['label'],
                    "required" => $field['required'] ?? false,
                    "nullable" => $field['nullable'] ?? true,
                    "input_type" => $field['input_type'],
                    "maxlength" => $field['maxlength'] ?? null,
                    "size" => $field['size'] ?? null,
                    "gridSize" => $field['gridSize'] ?? null,
                    "default" => $this->normalizeFieldDefault($field['default'] ?? null, $fieldType)
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

    /**
     * Save or update entity meta
     */
    private function saveEntityMeta($entity, $metaKey, $metaValue, $isCreate = true)
    {
        $metaData = [
            'meta_value' => $metaValue,
            'status' => 'active',
            'updated_by' => auth()->id()
        ];

        if ($isCreate) {
            $metaData['created_by'] = auth()->id();
        }

        $entity->metas()->updateOrCreate(
            ['meta_key' => $metaKey],
            $metaData
        );
    }

    /**
     * Create form schema and return its UID
     */
    private function createFormSchema($entity, $slug, $customFields, $metaFields = [])
    {
        $formSchema = $this->formSchemaGenerator->generate($entity, $customFields, $metaFields);
        $formSchemaRecord = FormSchema::create([
            'uid' => Str::ulid(),
            'slug' => $slug . '-form',
            'name' => $entity->entity_name . ' Form',
            'description' => 'Auto-generated form for ' . $entity->entity_name,
            'schema' => $formSchema,
            'extra_info' => [],
            'status' => EntityStatus::ACTIVE,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return $formSchemaRecord->uid;
    }

    private function ensureGeneratedFormSchema($entity, $customFields, $metaFields): void
    {
        $formSchemaUid = $entity->getMeta('form_schema_uid');
        $formSchemaSlug = $entity->slug . '-form';

        if (! $formSchemaUid) {
            $newFormSchemaUid = $this->createFormSchema($entity, $entity->slug, $customFields, $metaFields);
            $this->saveEntityMeta($entity, 'form_schema_uid', $newFormSchemaUid, false);

            Log::info('Generated form schema recreated because form_schema_uid was missing', [
                'entity_uid' => $entity->uid,
                'form_schema_uid' => $newFormSchemaUid,
            ]);

            return;
        }

        $formSchemaRecord = FormSchema::where('uid', $formSchemaUid)->first();

        if (! $formSchemaRecord) {
            $formSchemaRecord = FormSchema::where('slug', $formSchemaSlug)->first();
        }

        if (! $formSchemaRecord) {
            $newFormSchemaUid = $this->createFormSchema($entity, $entity->slug, $customFields, $metaFields);
            $this->saveEntityMeta($entity, 'form_schema_uid', $newFormSchemaUid, false);

            Log::info('Generated form schema recreated because stored schema record was missing', [
                'entity_uid' => $entity->uid,
                'missing_form_schema_uid' => $formSchemaUid,
                'new_form_schema_uid' => $newFormSchemaUid,
            ]);

            return;
        }

        $formSchemaRecord->update([
            'name' => $entity->entity_name . ' Form',
            'description' => 'Auto-generated form for ' . $entity->entity_name,
            'schema' => $this->formSchemaGenerator->generate($entity, $customFields, $metaFields),
            'updated_by' => auth()->id(),
        ]);

        if ($formSchemaRecord->uid !== $formSchemaUid) {
            $this->saveEntityMeta($entity, 'form_schema_uid', $formSchemaRecord->uid, false);
        }
    }

    /**
     * Create table schema and return its UID
     */
    private function createTableSchema($entity, $slug, $customFields, $formSchemaUid)
    {
        $tableSchema = $this->tableSchemaGenerator->generate($entity, $customFields, $formSchemaUid);
        $tableSchemaRecord = TableSchema::create([
            'uid' => Str::ulid(),
            'slug' => $slug . '-table',
            'name' => $entity->entity_name . ' Table',
            'description' => 'Auto-generated table for ' . $entity->entity_name,
            'schema' => $tableSchema,
            'extra_info' => [],
            'status' => EntityStatus::ACTIVE,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return $tableSchemaRecord->uid;
    }

    private function ensureGeneratedTableSchema($entity, $customFields, $formSchemaUid): void
    {
        $tableSchemaUid = $entity->getMeta('table_schema_uid');
        $tableSchemaSlug = $entity->slug . '-table';

        if (! $tableSchemaUid) {
            $newTableSchemaUid = $this->createTableSchema($entity, $entity->slug, $customFields, $formSchemaUid);
            $this->saveEntityMeta($entity, 'table_schema_uid', $newTableSchemaUid, false);

            Log::info('Generated table schema recreated because table_schema_uid was missing', [
                'entity_uid' => $entity->uid,
                'table_schema_uid' => $newTableSchemaUid,
            ]);

            return;
        }

        $tableSchemaRecord = TableSchema::where('uid', $tableSchemaUid)->first();

        if (! $tableSchemaRecord) {
            $tableSchemaRecord = TableSchema::where('slug', $tableSchemaSlug)->first();
        }

        if (! $tableSchemaRecord) {
            $newTableSchemaUid = $this->createTableSchema($entity, $entity->slug, $customFields, $formSchemaUid);
            $this->saveEntityMeta($entity, 'table_schema_uid', $newTableSchemaUid, false);

            Log::info('Generated table schema recreated because stored schema record was missing', [
                'entity_uid' => $entity->uid,
                'missing_table_schema_uid' => $tableSchemaUid,
                'new_table_schema_uid' => $newTableSchemaUid,
            ]);

            return;
        }

        $tableSchemaRecord->update([
            'name' => $entity->entity_name . ' Table',
            'description' => 'Auto-generated table for ' . $entity->entity_name,
            'schema' => $this->tableSchemaGenerator->generate($entity, $customFields, $formSchemaUid),
            'updated_by' => auth()->id(),
        ]);

        if ($tableSchemaRecord->uid !== $tableSchemaUid) {
            $this->saveEntityMeta($entity, 'table_schema_uid', $tableSchemaRecord->uid, false);
        }
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

            if (!$entity) {
                return redirect()
                ->back()
                ->with('error', 'Entity not found.');
            }

            return view('foundation::entity.show', [
                'entity' => $entity
            ]);

        } catch (\Throwable $th) {
            Log::error('Error fetching entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function generateFormSchemaFromEntity(string $entityUid)
    {
        try {
            $entity = Entity::with('metas')->where('uid', $entityUid)->firstOrFail();
            $customFields = $this->extractCustomFieldsFromEntityFields($entity->fields ?? []);
            $metaFields = is_array($entity->meta_fields) ? $entity->meta_fields : [];

            $this->ensureGeneratedFormSchema($entity, $customFields, $metaFields);

            return redirect()
                ->route('entities.show', $entity->uid)
                ->with('success', 'Form schema generated successfully.');
        } catch (Exception $e) {
            Log::error('Error generating form schema from entity', [
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

            $this->ensureGeneratedFormSchema($entity, $customFields, $metaFields);
            $formSchemaUid = $entity->getMeta('form_schema_uid');

            if (! $formSchemaUid) {
                throw new Exception('Unable to generate table schema without a form schema.');
            }

            $this->ensureGeneratedTableSchema($entity, $customFields, $formSchemaUid);

            return redirect()
                ->route('entities.show', $entity->uid)
                ->with('success', 'Table schema generated successfully.');
        } catch (Exception $e) {
            Log::error('Error generating table schema from entity', [
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

            Log::info('Entity marked as deleted', [
                'entity' => $entity->toArray()
            ]);

            return redirect()
                ->route('entities.index')
                ->with('success', 'Entity deleted successfully.');

        } catch (Exception $e) {
            Log::error('Error deleting entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function publish($entityUid)
    {
        try {
            Log::debug('Publishing entity', [
                'entityUid' => $entityUid
            ]);
            $entity = Entity::where('uid', $entityUid)->firstOrFail();

            // Check if entity is active
            if ($entity->status !== 'active') {
                return redirect()
                    ->back()
                    ->with('error', 'Only active entities can be published. Current status: ' . $entity->status);
            }

            // Get table name from entity meta
            $tableMeta = $entity->metas()->where('meta_key', 'table_name')->first();
            
            if (!$tableMeta) {
                return redirect()
                    ->back()
                    ->with('error', 'Table name not found in entity meta.');
            }

            $baseTableName = $tableMeta->meta_value;
            
            // Pluralize table name if not already plural
            $tableName = $this->pluralizeTableName($baseTableName);
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

            Log::info("Table '{$tableName}' created successfully", [
                'fields' => $orderedFields
            ]);

            // Create meta table
            Schema::create($metaTableName, function (Blueprint $table) use ($tableName) {
                $table->id();

                $table->unsignedBigInteger('ref_parent')->index();
                $table->string('meta_key')->index();
                $table->longText('meta_value')->nullable();
                $table->string('status')->default('unknown');

                $table->bigInteger('created_by')->default(0);
                $table->bigInteger('updated_by')->default(0);
                $table->bigInteger('deleted_by')->nullable();

                // DB-level timestamps (AUTO)
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->timestamp('deleted_at')->nullable();

                $table->index(['ref_parent', 'meta_key']);

                $table->foreign('ref_parent')
                    ->references('id')
                    ->on($tableName)
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            });

            Log::info("Meta table '{$metaTableName}' created successfully");

            // Update entity status to published
            $entity->update([
                'status' => 'published',
                'updated_by' => auth()->id()
            ]);

            // Save published status in entity meta
            $entity->metas()->updateOrCreate(
                ['meta_key' => 'published_at'],
                [
                    'meta_value' => now()->toDateTimeString(),
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id()
                ]
            );

            // Update table names in entity meta
            $entity->metas()->updateOrCreate(
                ['meta_key' => 'published_table_name'],
                [
                    'meta_value' => $tableName,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id()
                ]
            );

            $entity->metas()->updateOrCreate(
                ['meta_key' => 'published_meta_table_name'],
                [
                    'meta_value' => $metaTableName,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id()
                ]
            );

            return redirect()
                ->back()
                ->with('success', "Entity published successfully. Tables '{$tableName}' and '{$metaTableName}' created.");
                
        } catch (Exception $e) {
            Log::error('Error publishing entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()
                ->back()
                ->with('error', 'Error: ' . $e->getMessage());
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
            return $baseTableName . '_metas';
        } elseif (substr($baseTableName, -1) === 'y') {
            // category -> category_metas (keep singular for meta table relationship)
            return $baseTableName . '_metas';
        } else {
            return $baseTableName . '_metas';
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
                } else {
                    $column = $table->bigInteger($name)
                        ->default(in_array($name, ['created_by', 'updated_by']) ? 0 : null);
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
            !in_array($name, ['id', 'uid', 'created_by', 'updated_by'])
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
        if (in_array($name, ['status', 'created_by', 'deleted_at'])) {
            $column->index();
        }
    }
}
