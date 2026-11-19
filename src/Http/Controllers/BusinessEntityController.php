<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Iquesters\Foundation\Constants\EntityStatus;
use Iquesters\Foundation\Models\BusinessEntity;
use Iquesters\Foundation\Models\Entity;
use Iquesters\Foundation\Models\Module;
use Iquesters\Foundation\Services\BusinessEntity\BusinessEntityService;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinition;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityDefinitionValidator;
use Iquesters\Foundation\Support\BusinessEntity\BusinessEntityModels;
use Iquesters\Foundation\Support\BusinessEntity\InvalidBusinessEntityDefinition;
use Iquesters\Foundation\Support\BusinessEntity\Relations\RelationTypes;

/**
 * Admin screens for business entities: build one from registered entities
 * and platform models (root + linked parts + selected fields), review it,
 * save it, and (re)generate its form and table schemas. Records are created
 * and listed through the business entity API.
 */
class BusinessEntityController extends Controller
{
    public function __construct(private readonly BusinessEntityService $service) {}

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
        $businessEntity = $this->save(fn () => $this->service->create($data, $this->definitionInput($request)));

        return redirect()->route('business-entities.edit', $businessEntity->uid)
            ->with('success', 'Business entity created, with its form and table.');
    }

    public function update(Request $request, string $uid)
    {
        $businessEntity = $this->find($uid);
        $data = $this->validated($request, $businessEntity);
        $this->save(fn () => $this->service->update($businessEntity, $data, $this->definitionInput($request)));

        return redirect()->route('business-entities.edit', $businessEntity->uid)
            ->with('success', 'Business entity updated, its form and table were regenerated.');
    }

    public function destroy(string $uid)
    {
        $this->service->delete($this->find($uid));

        return redirect()->route('business-entities.index')->with('success', 'Business entity deleted. Its records were not changed.');
    }

    /**
     * Validate a definition without saving it (the builder's review step):
     * {valid, errors, missing_pivots, definition}.
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate(['definition' => ['required', 'json']]);

        try {
            $definition = $this->service->validate($this->definitionInput($request));

            return response()->json(['valid' => true, 'errors' => [], 'missing_pivots' => [], 'definition' => $definition->toArray()]);
        } catch (InvalidBusinessEntityDefinition $e) {
            return response()->json(['valid' => false, 'errors' => $e->problems, 'missing_pivots' => $e->missingPivots]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['valid' => false, 'errors' => [$e->getMessage()], 'missing_pivots' => []]);
        }
    }

    /** The columns of a table (link and pivot columns are picked from them): {exists, columns}. */
    public function columns(Request $request): JsonResponse
    {
        $table = (string) $request->query('table', '');

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) || ! Schema::hasTable($table)) {
            return response()->json(['exists' => false, 'columns' => []]);
        }

        return response()->json(['exists' => true, 'columns' => Schema::getColumnListing($table)]);
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

    private function definitionInput(Request $request): array
    {
        return (array) json_decode((string) $request->input('definition'), true);
    }

    /** Run a save; a definition that doesn't match the database is a validation error on "definition". */
    private function save(callable $save): BusinessEntity
    {
        try {
            return $save();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['definition' => $e->getMessage()]);
        }
    }

    /** View data: the entities, models and relation types the builder can use. */
    private function formData(?BusinessEntity $businessEntity): array
    {
        $entities = Entity::with('metas')
            ->where('status', '<>', EntityStatus::DELETED)
            ->orderBy('entity_name')
            ->get()
            ->map(function (Entity $entity) {
                $table = BusinessEntityDefinitionValidator::tableOf($entity);
                $published = $table !== null && Schema::hasTable($table);

                return [
                    'uid' => $entity->uid,
                    'name' => $entity->entity_name,
                    'table' => $table,
                    'published' => $published,
                    'columns' => $published ? Schema::getColumnListing($table) : [],
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

        $models = collect(app(BusinessEntityModels::class)->describe())
            ->map(fn ($m) => $m + ['columns' => Schema::hasTable($m['table']) ? Schema::getColumnListing($m['table']) : []])
            ->values();

        return [
            'businessEntity' => $businessEntity,
            'isCreating' => $businessEntity === null,
            'modules' => Module::where('status', EntityStatus::ACTIVE)->orderBy('name')->get(),
            'entities' => $entities,
            'models' => $models,
            'relationTypes' => app(RelationTypes::class)->describe(),
            'definition' => $businessEntity?->definition ?? ['version' => BusinessEntityDefinition::VERSION, 'parts' => []],
        ];
    }
}
