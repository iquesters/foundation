<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;
use Iquesters\Foundation\Models\Entity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Iquesters\UserInterface\Models\FormSchema;
use Iquesters\UserInterface\Models\TableSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Exception;

class EntityController extends Controller
{
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

            return view('foundation::entity.create-edit', [
                'entity' => null,
                'isCreating' => true
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

            return view('foundation::entity.create-edit', [
                'entity' => $entity,
                'isCreating' => false
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
                'slug' => 'required|string|max:255|unique:entities,slug',
                'desc' => 'nullable|string',
                'fields' => 'required|json',
                'meta_fields' => 'nullable|json',
            ]);

            // Generate table name from entity name
            $tableName = strtolower($validated['entity_name']);
            $tableName = preg_replace('/[^a-z0-9]+/', '_', $tableName);
            $tableName = trim($tableName, '_');

            // Define system default fields
            $systemFields = [
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

            // Decode custom fields from JSON
            $customFields = json_decode($validated['fields'], true);
            
            // Map frontend field types to database types
            $typeMapping = [
                'string' => 'varchar',
                'text' => 'text',
                'integer' => 'bigint',
                'decimal' => 'decimal',
                'boolean' => 'boolean',
                'date' => 'date',
                'datetime' => 'timestamp'
            ];

            // Process custom fields
            $processedCustomFields = [];
            if (is_array($customFields)) {
                foreach ($customFields as $field) {
                    $fieldName = $field['name'];
                    $processedCustomFields[$fieldName] = [
                        "name" => $fieldName,
                        "type" => $typeMapping[$field['type']] ?? $field['type'],
                        "label" => $field['label'],
                        "required" => $field['required'] ?? false,
                        "nullable" => $field['nullable'] ?? true,
                        "input_type" => $field['input_type'],
                        "maxlength" => $field['maxlength'] ?? null,
                        "default" => $field['default'] ?? null
                    ];
                }
            }

            // Merge system fields with custom fields
            $allFields = array_merge($systemFields, $processedCustomFields);

            // Decode meta fields
            $metaFieldsData = $validated['meta_fields'] 
                ? json_decode($validated['meta_fields'], true) 
                : [];

            // Create entity
            $entity = Entity::create([
                'uid' => Str::ulid(),
                'entity_name' => $validated['entity_name'],
                'slug' => $validated['slug'],
                'desc' => $validated['desc'] ?? null,
                'fields' => $allFields,
                'meta_fields' => $metaFieldsData,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            // Save table_name in entity_meta
            $entity->metas()->updateOrCreate(
                ['meta_key' => 'table_name'],
                [
                    'meta_value' => $tableName,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id()
                ]
            );

            // Generate Form Schema
            $formSchema = $this->generateFormSchema($entity, $processedCustomFields);
            $formSchemaRecord = FormSchema::create([
                'uid' => Str::ulid(),
                'slug' => $validated['slug'] . '-form',
                'name' => $validated['entity_name'] . ' Form',
                'description' => 'Auto-generated form for ' . $validated['entity_name'],
                'schema' => $formSchema,
                'extra_info' => [],
                'status' => 1,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Save form schema UID in entity meta
            $entity->metas()->updateOrCreate(
                ['meta_key' => 'form_schema_uid'],
                [
                    'meta_value' => $formSchemaRecord->uid,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id()
                ]
            );

            // Generate Table Schema
            $tableSchema = $this->generateTableSchema($entity, $processedCustomFields, $formSchemaRecord->uid);
            $tableSchemaRecord = TableSchema::create([
                'uid' => Str::ulid(),
                'slug' => $validated['slug'] . '-table',
                'name' => $validated['entity_name'] . ' Table',
                'description' => 'Auto-generated table for ' . $validated['entity_name'],
                'schema' => $tableSchema,
                'extra_info' => [],
                'status' => 'active',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Save table schema UID in entity meta
            $entity->metas()->updateOrCreate(
                ['meta_key' => 'table_schema_uid'],
                [
                    'meta_value' => $tableSchemaRecord->uid,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id()
                ]
            );

            Log::info('Entity created with schemas', [
                'entity' => $entity->toArray(),
                'table_name' => $tableName,
                'form_schema_uid' => $formSchemaRecord->uid,
                'table_schema_uid' => $tableSchemaRecord->uid
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

    /**
     * Generate form schema from custom fields
     */
    private function generateFormSchema($entity, $customFields)
    {
        $fields = [];
        
        // Map database input types to form input types
        $inputTypeMapping = [
            'text' => 'text',
            'number' => 'number',
            'email' => 'email',
            'datetime-local' => 'datetime-local',
            'date' => 'date',
        ];

        foreach ($customFields as $field) {
            $formField = [
                'id' => $field['name'],
                'type' => $inputTypeMapping[$field['input_type']] ?? 'text',
                'label' => $field['label'],
                'placeholder' => 'Enter ' . strtolower($field['label']),
                'helpertext' => $field['label'] . ' field',
                'required' => $field['required'],
                'size' => [
                    'md' => 12
                ]
            ];

            // Add validation rules
            if (isset($field['maxlength']) && $field['maxlength']) {
                $formField['maxLength'] = $field['maxlength'];
            }

            // Add messages
            $messages = [];
            if ($field['required']) {
                $messages['required'] = $field['label'] . ' is required';
            }
            if (!empty($messages)) {
                $formField['messages'] = $messages;
            }

            $fields[] = $formField;
        }

        return [
            'info' => [
                'icon' => 'far fa-lightbulb',
                'innerHTML' => 'You are creating a new ' . $entity->entity_name
            ],
            'endpoint' => 'post_' . url('/entities/' . $entity->slug . '/store'),
            'floatinglabel' => false,
            'enctype' => 'multipart/form-data',
            'header' => [
                'icon' => 'fas fa-database',
                'text' => 'Create ' . $entity->entity_name
            ],
            'fields' => $fields,
            'actions' => [
                [
                    'icon' => 'far fa-save',
                    'type' => 'submit',
                    'route' => '#',
                    'element' => [
                        'type' => 'button',
                        'color' => 'success'
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate table schema with system fields and first 2 custom fields
     */
    private function generateTableSchema($entity, $customFields, $formSchemaUid)
    {
        $columns = [];
        
        // Add ID column
        $columns[] = [
            'data' => 'id',
            'title' => 'ID',
            'visible' => true
        ];

        // Add first 2 custom fields (non-system fields)
        $customFieldArray = array_values($customFields);
        $fieldsToShow = array_slice($customFieldArray, 0, 2);
        
        foreach ($fieldsToShow as $field) {
            $columns[] = [
                'data' => $field['name'],
                'title' => $field['label'],
                'visible' => true,
                'link' => true,
                'form-schema-uid' => $formSchemaUid
            ];
        }

        // Add Status column
        $columns[] = [
            'data' => 'status',
            'title' => 'Status',
            'visible' => true
        ];

        return [
            'entity' => $entity->slug,
            'dt-options' => [
                'columns' => $columns,
                'options' => [
                    'pageLength' => 10,
                    'order' => [[0, 'desc']],
                    'responsive' => true
                ]
            ],
            'default_view_mode' => 'inbox'
        ];
    }

    public function update(Request $request, $uid)
    {
        try {
            $entity = Entity::where('uid', $uid)->firstOrFail();

            $validated = $request->validate([
                'entity_name' => 'required|string|max:255|unique:entities,entity_name,' . $entity->id,
                'slug' => 'required|string|max:255|unique:entities,slug,' . $entity->id,
                'desc' => 'nullable|string',
                'fields' => 'required|json',
                'meta_fields' => 'nullable|json',
            ]);

            // Generate table name from entity name
            $tableName = strtolower($validated['entity_name']);
            $tableName = preg_replace('/[^a-z0-9]+/', '_', $tableName);
            $tableName = trim($tableName, '_');

            // Define system default fields
            $systemFields = [
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

            // Decode custom fields from JSON
            $customFields = json_decode($validated['fields'], true);
            
            // Map frontend field types to database types
            $typeMapping = [
                'string' => 'varchar',
                'text' => 'text',
                'integer' => 'bigint',
                'decimal' => 'decimal',
                'boolean' => 'boolean',
                'date' => 'date',
                'datetime' => 'timestamp'
            ];

            // Process custom fields
            $processedCustomFields = [];
            if (is_array($customFields)) {
                foreach ($customFields as $field) {
                    $fieldName = $field['name'];
                    $processedCustomFields[$fieldName] = [
                        "name" => $fieldName,
                        "type" => $typeMapping[$field['type']] ?? $field['type'],
                        "label" => $field['label'],
                        "required" => $field['required'] ?? false,
                        "nullable" => $field['nullable'] ?? true,
                        "input_type" => $field['input_type'],
                        "maxlength" => $field['maxlength'] ?? null,
                        "default" => $field['default'] ?? null
                    ];
                }
            }

            // Merge system fields with custom fields
            $allFields = array_merge($systemFields, $processedCustomFields);

            // Decode meta fields
            $metaFieldsData = $validated['meta_fields'] 
                ? json_decode($validated['meta_fields'], true) 
                : [];

            // Update entity
            $entity->update([
                'entity_name' => $validated['entity_name'],
                'slug' => $validated['slug'],
                'desc' => $validated['desc'] ?? null,
                'fields' => $allFields,
                'meta_fields' => $metaFieldsData,
                'updated_by' => auth()->id(),
            ]);

            // Update table_name in entity_meta
            $entity->metas()->updateOrCreate(
                ['meta_key' => 'table_name'],
                [
                    'meta_value' => $tableName,
                    'status' => 'active',
                    'updated_by' => auth()->id()
                ]
            );

            Log::info('Entity updated', [
                'entity' => $entity->toArray(),
                'table_name' => $tableName
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
                $table->bigInteger('created_by')->nullable();
                $table->bigInteger('updated_by')->nullable();
                $table->bigInteger('deleted_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->timestamp('deleted_at')->nullable();

                // Add composite index for better query performance
                $table->index(['ref_parent', 'meta_key']);
                
                // Add foreign key constraint
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
     * Pluralize table name if not already plural
     */
    private function pluralizeTableName($tableName)
    {
        // Common plural endings
        $pluralEndings = ['s', 'es', 'ies'];
        
        foreach ($pluralEndings as $ending) {
            if (substr($tableName, -strlen($ending)) === $ending) {
                return $tableName; // Already plural
            }
        }
        
        // Simple pluralization rules
        if (substr($tableName, -1) === 'y') {
            return substr($tableName, 0, -1) . 'ies'; // category -> categories
        } elseif (in_array(substr($tableName, -1), ['s', 'x', 'z']) || 
                in_array(substr($tableName, -2), ['sh', 'ch'])) {
            return $tableName . 'es'; // box -> boxes, dish -> dishes
        } else {
            return $tableName . 's'; // test -> tests
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

            case 'decimal':
                $column = $table->decimal($name, 10, 2);
                break;

            case 'boolean':
                $column = $table->boolean($name);
                break;

            case 'date':
                $column = $table->date($name);
                break;

            case 'timestamp':
                $column = $table->timestamp($name);
                break;

            default:
                $column = $table->string($name);
                break;
        }

        // Apply nullable
        if ($nullable && $name !== 'id') {
            $column->nullable();
        }

        // Apply default value
        if ($default !== null && $name !== 'id' && $name !== 'uid') {
            // Remove quotes from default value if present
            $defaultValue = trim($default, "'\"");
            
            if ($defaultValue === 'NULL') {
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