<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Contracts\SchemaModels;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\Models\Module;
use Iquesters\Foundation\Services\BusinessEntitySchemaGenerator;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinitionValidator;
use Iquesters\Foundation\System\Traits\Loggable;

/**
 * Admin screens for business entities: build one from registered entities
 * (root + linked parts + selected fields), save it, and (re)generate its form
 * and table schemas. Records are created and listed through the business
 * entity API in iquesters/user-interface.
 */
class BusinessEntityController extends Controller
{
    use Loggable;

    public function __construct(
        private readonly BusinessEntityDefinitionValidator $validator,
        private readonly BusinessEntitySchemaGenerator $schemas,
    ) {}

    public function index()
    {
        $businessEntities = BusinessEntity::with(['module', 'metas'])->notDeleted()->orderBy('name')->get();

        return view('foundation::business_entity.index', ['businessEntities' => $businessEntities]);
    }

    public function create()
    {
        return view('foundation::business_entity.create-edit', $this->formData(null));
    }

    public function edit(string $uid)
    {
        return view('foundation::business_entity.create-edit', $this->formData($this->find($uid)));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $definition = $this->definition($request);

        $businessEntity = DB::transaction(function () use ($data, $definition) {
            $businessEntity = BusinessEntity::create([
                'uid' => (string) Str::ulid(),
                'ref_module' => $data['ref_module'] ?? null,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'desc' => $data['desc'] ?? null,
                'definition' => $definition->toArray(),
                'status' => EntityStatus::ACTIVE,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncSchemas($businessEntity, $definition);

            return $businessEntity;
        });

        static::logInfo('Business entity created', ['uid' => $businessEntity->uid, 'slug' => $businessEntity->slug]);

        return redirect()->route('business-entities.edit', $businessEntity->uid)
            ->with('success', 'Business entity created, with its form and table.');
    }

    public function update(Request $request, string $uid)
    {
        $businessEntity = $this->find($uid);
        $data = $this->validated($request, $businessEntity);
        $definition = $this->definition($request);

        DB::transaction(function () use ($businessEntity, $data, $definition) {
            // The slug stays: it is in the API URLs and the schema slugs.
            $businessEntity->update([
                'ref_module' => $data['ref_module'] ?? null,
                'name' => $data['name'],
                'desc' => $data['desc'] ?? null,
                'definition' => $definition->toArray(),
                'updated_by' => auth()->id(),
            ]);

            $this->syncSchemas($businessEntity, $definition);
        });

        static::logInfo('Business entity updated', ['uid' => $businessEntity->uid]);

        return redirect()->route('business-entities.edit', $businessEntity->uid)
            ->with('success', 'Business entity updated, its form and table were regenerated.');
    }

    public function destroy(string $uid)
    {
        $businessEntity = $this->find($uid);
        $businessEntity->update(['status' => EntityStatus::DELETED, 'updated_by' => auth()->id()]);

        static::logInfo('Business entity deleted', ['uid' => $businessEntity->uid]);

        return redirect()->route('business-entities.index')->with('success', 'Business entity deleted. Its records were not changed.');
    }

    private function find(string $uid): BusinessEntity
    {
        return BusinessEntity::where('uid', $uid)->notDeleted()->firstOrFail();
    }

    private function validated(Request $request, ?BusinessEntity $current = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('business_entities', 'name')
                    ->where(fn ($q) => $q->where('status', '<>', EntityStatus::DELETED))
                    ->ignore($current?->id),
            ],
            'ref_module' => ['nullable', 'integer', 'exists:modules,id'],
            'desc' => ['nullable', 'string', 'max:255'],
            'definition' => ['required', 'json'],
        ]);
    }

    /** The submitted definition, checked against the registered entities (errors on "definition"). */
    private function definition(Request $request): BusinessEntityDefinition
    {
        try {
            return $this->validator->validate((array) json_decode((string) $request->input('definition'), true));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['definition' => $e->getMessage()]);
        }
    }

    private function syncSchemas(BusinessEntity $businessEntity, BusinessEntityDefinition $definition): void
    {
        // Schemas are stored by iquesters/user-interface; without it there is nothing to generate.
        if (app()->bound(SchemaModels::class)) {
            $this->schemas->sync($businessEntity, $definition);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business-entity';
        $slug = $base;

        for ($i = 2; BusinessEntity::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /** View data, including the published entities the builder can use. */
    private function formData(?BusinessEntity $businessEntity): array
    {
        $entities = Entity::with('metas')
            ->where('status', '<>', EntityStatus::DELETED)
            ->orderBy('entity_name')
            ->get()
            ->map(function (Entity $entity) {
                $table = BusinessEntityDefinitionValidator::tableOf($entity);

                return [
                    'uid' => $entity->uid,
                    'name' => $entity->entity_name,
                    'table' => $table,
                    'published' => $table !== null && Schema::hasTable($table),
                    'fields' => array_values(array_map(fn ($f) => [
                        'name' => $f['name'],
                        'label' => $f['label'] ?? Str::headline($f['name']),
                        'type' => $f['type'] ?? 'string',
                        'system' => in_array($f['name'], BusinessEntityDefinition::SYSTEM_COLUMNS, true),
                    ], BusinessEntityDefinitionValidator::mainFields($entity))),
                    'meta_fields' => array_values(array_map(fn ($f) => [
                        'meta_key' => $f['meta_key'],
                        'label' => $f['label'] ?? Str::headline($f['meta_key']),
                        'type' => $f['type'] ?? 'string',
                    ], BusinessEntityDefinitionValidator::metaFields($entity))),
                ];
            })
            ->values();

        return [
            'businessEntity' => $businessEntity,
            'isCreating' => $businessEntity === null,
            'modules' => Module::where('status', EntityStatus::ACTIVE)->orderBy('name')->get(),
            'entities' => $entities,
            'definition' => $businessEntity?->definition ?? ['version' => 1, 'parts' => []],
        ];
    }
}
