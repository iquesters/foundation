<?php

namespace Iquesters\Foundation\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\Models\Module;
use Iquesters\Foundation\Services\FormSchemaService;
use Iquesters\Foundation\Services\TableSchemaService;
use Iquesters\Foundation\System\Traits\Loggable;

class BusinessEntityController extends Controller
{
    use Loggable;

    public function __construct(
        private FormSchemaService $formSchemaService,
        private TableSchemaService $tableSchemaService
    ) {
    }

    public function index()
    {
        try {
            $businessEntities = BusinessEntity::query()->get();

            return view('foundation::business_entity.index', [
                'businessEntities' => $businessEntities,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching business entities', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'An error occurred while fetching business entities.');
        }
    }

    public function create()
    {
        $modules = Module::where('status', 'active')->get();
        $entities = Entity::with('metas')
            ->where('status', 'active')
            ->get();

        return view('foundation::business_entity.create-edit', [
            'businessEntity' => null,
            'isCreating' => true,
            'modules' => $modules,
            'entities' => $entities,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'business_entity_name' => 'required|string|max:255|unique:business_entities,business_entity_name',
                'ref_module' => 'required|exists:modules,id',
                'desc' => 'nullable|string',
                'field_mapping' => 'required|json',
            ]);

            $fieldMapping = json_decode($validated['field_mapping'], true);
            $slug = $this->generateUniqueSlug($validated['business_entity_name']);
            $tableName = $this->generateTableName($validated['business_entity_name']);

            $this->logInfo('Creating business entity ' . $validated['business_entity_name']);

            $businessEntity = BusinessEntity::create([
                'uid' => (string) Str::ulid(),
                'ref_module' => $validated['ref_module'],
                'business_entity_name' => $validated['business_entity_name'],
                'slug' => $slug,
                'desc' => $validated['desc'] ?? null,
                'field_mapping' => $fieldMapping,
                'status' => 'active',
                'created_by' => auth()->id() ?? 0,
                'updated_by' => auth()->id() ?? 0,
            ]);

            [$customFields, $metaFields] = $this->buildSchemaFieldsFromMapping($fieldMapping);
            $this->saveBusinessEntityMeta($businessEntity, 'table_name', $tableName);

            $this->logInfo('Generating schemas for business entity ' . $businessEntity->business_entity_name);

            $schemaOptions = ['is_business_entity' => true];
            $formSchemaUid = $this->formSchemaService->createAndAttach(
                $businessEntity,
                $slug,
                $customFields,
                $metaFields,
                $schemaOptions
            );
            $tableSchemaUid = $this->tableSchemaService->createAndAttach(
                $businessEntity,
                $slug,
                $customFields,
                $formSchemaUid,
                $metaFields,
                $schemaOptions
            );

            Log::info('Business entity created with schemas', [
                'business_entity_uid' => $businessEntity->uid,
                'form_schema_uid' => $formSchemaUid,
                'table_schema_uid' => $tableSchemaUid,
            ]);

            return redirect()
                ->route('business-entities.edit', $businessEntity->uid)
                ->with('success', 'Business Entity created successfully with form and table schemas.');
        } catch (Exception $e) {
            Log::error('Error creating business entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function edit(string $businessEntityUid)
    {
        try {
            $businessEntity = BusinessEntity::where('uid', $businessEntityUid)->firstOrFail();
            $modules = Module::where('status', 'active')->get();
            $entities = Entity::with('metas')
                ->where('status', 'active')
                ->get();

            return view('foundation::business_entity.create-edit', [
                'businessEntity' => $businessEntity,
                'isCreating' => false,
                'modules' => $modules,
                'entities' => $entities,
            ]);
        } catch (Exception $e) {
            Log::error('Error loading business entity edit form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->route('business-entities.index')
                ->with('error', 'An error occurred while loading the business entity.');
        }
    }

    public function update(Request $request, string $businessEntityUid)
    {
        try {
            $businessEntity = BusinessEntity::where('uid', $businessEntityUid)->firstOrFail();

            $validated = $request->validate([
                'business_entity_name' => 'required|string|max:255|unique:business_entities,business_entity_name,' . $businessEntity->id,
                'ref_module' => 'required|exists:modules,id',
                'desc' => 'nullable|string',
                'field_mapping' => 'required|json',
            ]);

            $businessEntity->update([
                'ref_module' => $validated['ref_module'],
                'business_entity_name' => $validated['business_entity_name'],
                'desc' => $validated['desc'] ?? null,
                'field_mapping' => json_decode($validated['field_mapping'], true),
                'updated_by' => auth()->id() ?? 0,
            ]);

            [$customFields, $metaFields] = $this->buildSchemaFieldsFromMapping($validated['field_mapping']);
            $schemaOptions = ['is_business_entity' => true];

            $formSchemaUid = $this->formSchemaService->ensureGenerated(
                $businessEntity,
                $customFields,
                $metaFields,
                $schemaOptions
            );

            $this->tableSchemaService->ensureGenerated(
                $businessEntity,
                $businessEntity->slug,
                $customFields,
                $formSchemaUid,
                $metaFields,
                $schemaOptions
            );

            return redirect()
                ->route('business-entities.edit', $businessEntity->uid)
                ->with('success', 'Business Entity updated successfully.');
        } catch (Exception $e) {
            Log::error('Error updating business entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(string $businessEntityUid)
    {
        try {
            $businessEntity = BusinessEntity::where('uid', $businessEntityUid)->firstOrFail();

            $businessEntity->update([
                'status' => 'deleted',
                'updated_by' => auth()->id() ?? 0,
            ]);

            return redirect()
                ->route('business-entities.index')
                ->with('success', 'Business Entity deleted successfully.');
        } catch (Exception $e) {
            Log::error('Error deleting business entity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function entityOptions()
    {
        $entities = Entity::where('status', 'active')
            ->get()
            ->map(function (Entity $entity) {
                return [
                    'uid' => $entity->uid,
                    'entity_name' => $entity->entity_name,
                    'slug' => $entity->slug,
                    'fields' => $entity->fields ?? [],
                    'meta_fields' => $entity->meta_fields ?? [],
                ];
            });

        return response()->json([
            'data' => $entities,
        ]);
    }

    public function buildSchemaFieldsFromMapping(mixed $fieldMapping): array
    {
        if (is_string($fieldMapping)) {
            $fieldMapping = json_decode($fieldMapping, true) ?: [];
        }

        $customFields = [];
        $metaFields = [];
        $entities = $fieldMapping['entities'] ?? [];

        if (! is_array($entities)) {
            return [$customFields, $metaFields];
        }

        usort($entities, function ($left, $right) {
            return ($left['sort_order'] ?? 0) <=> ($right['sort_order'] ?? 0);
        });

        foreach ($entities as $mappedEntity) {
            if (! is_array($mappedEntity)) {
                continue;
            }

            $prefix = $this->resolveMappingPrefix($mappedEntity);
            $entityLabel = $mappedEntity['entity'] ?? $prefix;

            foreach (($mappedEntity['fields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldName = $field['field'] ?? $field['name'] ?? null;

                if (! $fieldName) {
                    continue;
                }

                $schemaFieldName = $this->normalizeSchemaFieldName($prefix . '_' . $fieldName);
                $fieldType = $field['type'] ?? 'string';

                $customFields[$schemaFieldName] = [
                    'name' => $schemaFieldName,
                    'type' => $fieldType,
                    'label' => trim(($entityLabel ? $entityLabel . ' ' : '') . ($field['label'] ?? Str::headline($fieldName))),
                    'required' => $field['required'] ?? false,
                    'nullable' => $field['nullable'] ?? true,
                    'input_type' => $field['input_type'] ?? $this->resolveInputType($fieldType),
                    'maxlength' => $field['maxlength'] ?? null,
                    'default' => $field['default'] ?? null,
                    'source_entity' => $mappedEntity['entity'] ?? null,
                    'source_field' => $fieldName,
                ];
            }

            foreach (($mappedEntity['meta_fields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $metaKey = $field['meta_key'] ?? $field['field'] ?? $field['name'] ?? null;

                if (! $metaKey) {
                    continue;
                }

                $schemaMetaKey = $this->normalizeSchemaFieldName($prefix . '_m_' . $metaKey);
                $fieldType = $field['type'] ?? 'string';

                $metaFields[] = [
                    'meta_key' => $schemaMetaKey,
                    'label' => trim(($entityLabel ? $entityLabel . ' ' : '') . ($field['label'] ?? Str::headline($metaKey))),
                    'type' => $fieldType,
                    'input_type' => $field['input_type'] ?? $this->resolveInputType($fieldType),
                    'required' => $field['required'] ?? false,
                    'nullable' => $field['nullable'] ?? true,
                    'display' => $field['display'] ?? true,
                    'source_entity' => $mappedEntity['entity'] ?? null,
                    'source_meta_key' => $metaKey,
                ];
            }
        }

        return [$customFields, $metaFields];
    }

    private function resolveMappingPrefix(array $mappedEntity): string
    {
        return $this->normalizeSchemaFieldName(
            $mappedEntity['slug']
            ?? $mappedEntity['entity']
            ?? $mappedEntity['entity_uid']
            ?? 'entity'
        );
    }

    private function normalizeSchemaFieldName(string $fieldName): string
    {
        $fieldName = strtolower($fieldName);
        $fieldName = preg_replace('/[^a-z0-9]+/', '_', $fieldName);

        return trim($fieldName, '_');
    }

    private function resolveInputType(string $fieldType): string
    {
        return match ($fieldType) {
            'text', 'longtext' => 'textarea',
            'integer', 'bigint', 'decimal' => 'number',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime-local',
            'time' => 'time',
            'boolean' => 'checkbox',
            default => 'text',
        };
    }

    private function saveBusinessEntityMeta(BusinessEntity $businessEntity, string $metaKey, mixed $metaValue): void
    {
        $businessEntity->metas()->updateOrCreate(
            ['meta_key' => $metaKey],
            [
                'meta_value' => $metaValue,
                'status' => 'active',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );
    }

    private function generateTableName(string $businessEntityName): string
    {
        $tableName = strtolower($businessEntityName);
        $tableName = preg_replace('/[^a-z0-9]+/', '_', $tableName);

        return trim($tableName, '_');
    }

    private function generateUniqueSlug(string $businessEntityName): string
    {
        $baseSlug = strtolower($businessEntityName);
        $baseSlug = preg_replace('/[^a-z0-9]+/', '-', $baseSlug);
        $baseSlug = trim($baseSlug, '-');

        $slug = $baseSlug;
        $counter = 1;

        while (BusinessEntity::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

}
