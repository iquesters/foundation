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

class BusinessEntityController extends Controller
{
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
        $entities = Entity::where('status', 'active')->get();

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

            $businessEntity = BusinessEntity::create([
                'uid' => (string) Str::ulid(),
                'ref_module' => $validated['ref_module'],
                'business_entity_name' => $validated['business_entity_name'],
                'slug' => $this->generateUniqueSlug($validated['business_entity_name']),
                'desc' => $validated['desc'] ?? null,
                'field_mapping' => json_decode($validated['field_mapping'], true),
                'status' => 'active',
                'created_by' => auth()->id() ?? 0,
                'updated_by' => auth()->id() ?? 0,
            ]);

            return redirect()
                ->route('business-entities.edit', $businessEntity->uid)
                ->with('success', 'Business Entity created successfully.');
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
            $entities = Entity::where('status', 'active')->get();

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
