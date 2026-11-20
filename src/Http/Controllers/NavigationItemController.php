<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Iquesters\Foundation\Models\Module;
use Iquesters\Foundation\Models\NavigationItem;
use Iquesters\Foundation\Services\Navigation\NavigationSync;

/**
 * Admin → Navigation: each module's sidebar items and their tabs (child
 * items). Admins add, edit, reorder and hide items; a module item they change
 * is marked customized, so module updates keep the change. "Restore" gives a
 * module item back to its module.
 *
 * System items (a module's Dashboard, an item's own-page tab) can only be
 * moved: editing, hiding and deleting them is refused.
 */
class NavigationItemController extends Controller
{
    public function index(Request $request)
    {
        $modules = Module::orderBy('name')->get();
        $module = $modules->firstWhere('uid', $request->query('module')) ?? $modules->first();
        $items = $module
            ? NavigationItem::with(['children' => fn ($q) => $q->orderBy('sort')])
                ->where('ref_module', $module->id)->whereNull('ref_parent')->orderBy('sort')->orderBy('id')->get()
            : collect();

        return view('foundation::navigation_items.index', compact('modules', 'module', 'items'));
    }

    public function create(Request $request)
    {
        $module = Module::where('uid', $request->query('module'))->firstOrFail();
        $item = new NavigationItem(['status' => 'active', 'sort' => $this->nextSort($module, $request->query('parent'))]);
        $item->ref_parent = NavigationItem::where('uid', $request->query('parent'))->value('id');

        return view('foundation::navigation_items.form', $this->formData($module, $item));
    }

    public function store(Request $request, NavigationSync $sync)
    {
        $module = Module::where('uid', $request->input('module'))->firstOrFail();
        $data = $this->validated($request, $module);

        $item = NavigationItem::create($data + [
            'uid' => (string) Str::ulid(),
            'ref_module' => $module->id,
            'key' => $module->name.'.custom-'.Str::lower((string) Str::ulid()),
            'source' => NavigationItem::SOURCE_ADMIN,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        // The first tab of an item brings its own-page tab with it.
        if ($item->ref_parent) {
            $sync->ensureSystemItems($module);
        }

        return redirect()->route('navigation-items.index', ['module' => $module->uid])
            ->with('success', "\"{$item->label}\" was added.");
    }

    public function edit(string $uid)
    {
        $item = NavigationItem::where('uid', $uid)->firstOrFail();
        $this->refuseLocked($item);

        return view('foundation::navigation_items.form', $this->formData($item->module, $item));
    }

    public function update(Request $request, string $uid)
    {
        $item = NavigationItem::where('uid', $uid)->firstOrFail();
        $this->refuseLocked($item);
        $data = $this->validated($request, $item->module, $item);

        // A module's item that an admin changed keeps the change on module updates.
        $item->update($data + ['customized' => $item->source === NavigationItem::SOURCE_MODULE, 'updated_by' => auth()->id()]);

        return redirect()->route('navigation-items.index', ['module' => $item->module->uid])
            ->with('success', "\"{$item->label}\" was saved.");
    }

    /** Admin items are deleted; module items are hidden (they would come back on the next sync otherwise). */
    public function destroy(string $uid)
    {
        $item = NavigationItem::where('uid', $uid)->firstOrFail();
        $this->refuseLocked($item);
        $module = $item->module;

        if ($item->source === NavigationItem::SOURCE_ADMIN) {
            $item->delete();
            $message = "\"{$item->label}\" was deleted.";
        } else {
            $item->update(['status' => 'inactive', 'customized' => true, 'updated_by' => auth()->id()]);
            $message = "\"{$item->label}\" is hidden. Edit it to show it again, or restore the module's version.";
        }

        return redirect()->route('navigation-items.index', ['module' => $module->uid])->with('success', $message);
    }

    /** Give a module's item back to the module: its label, target, order and status as the module declares them. */
    public function restore(string $uid, NavigationSync $sync)
    {
        $item = NavigationItem::where('uid', $uid)->where('source', NavigationItem::SOURCE_MODULE)->firstOrFail();
        $item->update(['customized' => false]);
        $sync->syncModule($item->module);

        return redirect()->route('navigation-items.index', ['module' => $item->module->uid])
            ->with('success', "\"{$item->fresh()->label}\" is back to the module's version.");
    }

    /** Save the order after drag and drop: {order: [uid, …]} within one level. */
    public function reorder(Request $request)
    {
        $uids = $request->validate(['order' => ['required', 'array'], 'order.*' => ['string']])['order'];

        DB::transaction(function () use ($uids) {
            foreach (array_values($uids) as $position => $uid) {
                $item = NavigationItem::where('uid', $uid)->first();
                $item?->update([
                    'sort' => ($position + 1) * 10,
                    'customized' => $item->source === NavigationItem::SOURCE_MODULE,
                    'updated_by' => auth()->id(),
                ]);
            }
        });

        return response()->json(['success' => true]);
    }

    private function refuseLocked(NavigationItem $item): void
    {
        abort_if($item->isLocked(), 403, 'This item is fixed: it can be moved, not changed.');
    }

    private function validated(Request $request, Module $module, ?NavigationItem $item = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9 -]+$/'],
            'parent' => ['nullable', 'string'],
            'target' => ['required', Rule::in(['route', 'table', 'url'])],
            'route' => ['nullable', 'required_if:target,route', 'string', 'max:191'],
            'params' => ['nullable', 'json'],
            'table_schema_uid' => ['nullable', 'required_if:target,table', 'string', 'max:64'],
            'url' => ['nullable', 'required_if:target,url', 'string', 'max:2048', 'regex:#^(https?://|/)#'],
            'permission' => ['nullable', 'string', 'max:191'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'url.regex' => 'The link must start with / (a page of this app) or http(s)://.',
            'icon.regex' => 'Use Font Awesome classes, e.g. "fas fa-users".',
        ]);

        if ($data['target'] === 'route' && ! Route::has($data['route'])) {
            throw ValidationException::withMessages(['route' => "There is no page (route) named \"{$data['route']}\"."]);
        }

        if ($data['target'] === 'table' && Schema::hasTable('table_schemas')
            && ! DB::table('table_schemas')->where('uid', $data['table_schema_uid'])->exists()) {
            throw ValidationException::withMessages(['table_schema_uid' => 'Choose an existing table.']);
        }

        $parentId = null;
        if (! empty($data['parent'])) {
            $parent = NavigationItem::where('uid', $data['parent'])->where('ref_module', $module->id)->whereNull('ref_parent')
                ->where('source', '<>', NavigationItem::SOURCE_SYSTEM)->first();
            if (! $parent || $parent->id === $item?->id) {
                throw ValidationException::withMessages(['parent' => 'Choose a sidebar item of this module (tabs go one level deep).']);
            }
            $parentId = $parent->id;
        }

        return [
            'label' => $data['label'],
            'icon' => $data['icon'] ?? null,
            'ref_parent' => $parentId,
            'route' => $data['target'] === 'route' ? $data['route'] : null,
            'params' => $data['target'] === 'route' && ! empty($data['params']) ? json_decode($data['params'], true) : null,
            'table_schema_uid' => $data['target'] === 'table' ? $data['table_schema_uid'] : null,
            'url' => $data['target'] === 'url' ? $data['url'] : null,
            'permission' => $data['permission'] ?: null,
            'sort' => $data['sort'] ?? ($item?->sort ?? 0),
            'status' => $data['status'],
        ];
    }

    private function formData(Module $module, NavigationItem $item): array
    {
        return [
            'module' => $module,
            'item' => $item,
            'parents' => NavigationItem::where('ref_module', $module->id)->whereNull('ref_parent')
                ->where('source', '<>', NavigationItem::SOURCE_SYSTEM)
                ->when($item->exists, fn ($q) => $q->where('id', '<>', $item->id))->orderBy('sort')->get(),
            'tableSchemas' => Schema::hasTable('table_schemas')
                ? DB::table('table_schemas')->where('status', '<>', 'deleted')->orderBy('name')->pluck('name', 'uid')
                : collect(),
            'permissions' => Schema::hasTable('permissions') ? DB::table('permissions')->orderBy('name')->pluck('name') : collect(),
            'target' => $item->url ? 'url' : ($item->table_schema_uid ? 'table' : 'route'),
        ];
    }

    private function nextSort(Module $module, ?string $parentUid): int
    {
        $parentId = $parentUid ? NavigationItem::where('uid', $parentUid)->value('id') : null;

        return (int) NavigationItem::where('ref_module', $module->id)->where('ref_parent', $parentId)->max('sort') + 10;
    }
}
