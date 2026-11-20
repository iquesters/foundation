<?php

namespace Iquesters\Foundation\Services\Navigation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iquesters\Foundation\Models\Module;
use Iquesters\Foundation\Models\NavigationItem;
use Iquesters\Foundation\System\Traits\Loggable;

/**
 * Copies the navigation modules declare into navigation_items.
 *
 * A module declares its sidebar in its seeder (module meta
 * module_sidebar_menu); an entry may list "children": the tabs of that page.
 *
 *     ['key' => 'jobs', 'label' => 'Jobs', 'icon' => '…', 'route' => 'jobs.index',
 *      'permission' => 'view-jobs',
 *      'children' => [
 *          ['label' => 'Current', 'route' => 'jobs.index'],
 *          ['label' => 'Failed', 'route' => 'jobs.failed'],
 *      ]]
 *
 * Rules:
 * - an item keeps its identity by "key" ("{module}.{key}", else
 *   "{module}.{label as slug}"; children add their own after the parent's);
 * - new items are created, items the module still owns are updated;
 * - an item an admin changed (customized) is left as it is;
 * - a module item the module no longer declares is hidden (status
 *   inactive), not deleted; admin items are never touched;
 * - the first import keeps the order saved in the old navigation screen.
 *
 * Every module also gets fixed (system) items, see ensureSystemItems():
 * a Dashboard at the top of its sidebar, and under each item with tabs an
 * "own page" tab. Admins can move them, not edit or hide them.
 */
class NavigationSync
{
    use Loggable;

    public function available(): bool
    {
        return Schema::hasTable('navigation_items') && Schema::hasTable('module_metas');
    }

    public function syncAll(): void
    {
        if (! $this->available()) {
            return;
        }

        foreach (Module::all() as $module) {
            $this->syncModule($module);
        }
    }

    public function syncModule(Module $module): void
    {
        if (! $this->available()) {
            return;
        }

        $declared = json_decode((string) DB::table('module_metas')
            ->where('ref_parent', $module->id)
            ->where('meta_key', 'module_sidebar_menu')
            ->value('meta_value'), true);

        $seen = [];

        DB::transaction(function () use ($module, $declared, &$seen) {
            $this->syncLevel($module, is_array($declared) ? array_values($declared) : [], null, $module->name, $this->legacyOrder($module), $seen);

            // Gone from the module's declaration: hidden (admins' own items and edits stay).
            NavigationItem::where('ref_module', $module->id)
                ->where('source', NavigationItem::SOURCE_MODULE)
                ->where('customized', false)
                ->whereNotIn('key', $seen)
                ->update(['status' => 'inactive', 'updated_at' => now()]);

            $this->ensureSystemItems($module);
        });
    }

    /**
     * The fixed items of a module, created once and then left where the admin
     * moved them:
     * - "{module}.dashboard": the module's dashboard, first in the sidebar;
     * - "{item}.self" under each sidebar item that has tabs: the item's own
     *   page as a tab, first unless moved. Not added when a tab already opens
     *   the item's page (e.g. Jobs → "Current").
     */
    public function ensureSystemItems(Module $module): void
    {
        if (! $this->available()) {
            return;
        }

        $dashboardKey = $module->name.'.dashboard';

        if (! NavigationItem::where('key', $dashboardKey)->exists()) {
            NavigationItem::create([
                'uid' => (string) Str::ulid(),
                'ref_module' => $module->id,
                'key' => $dashboardKey,
                'label' => 'Dashboard',
                'icon' => 'fas fa-gauge',
                'route' => NavigationItem::DASHBOARD_ROUTE,
                'params' => ['module' => $module->name],
                'sort' => (int) NavigationItem::where('ref_module', $module->id)->whereNull('ref_parent')->min('sort') - 10,
                'status' => 'active',
                'source' => NavigationItem::SOURCE_SYSTEM,
            ]);
        }

        $parents = NavigationItem::with('children')
            ->where('ref_module', $module->id)
            ->whereNull('ref_parent')
            ->where('source', '<>', NavigationItem::SOURCE_SYSTEM)
            ->get();

        foreach ($parents as $parent) {
            $selfKey = $parent->key.'.self';
            $tabs = $parent->children->reject(fn (NavigationItem $child) => $child->key === $selfKey);

            if ($tabs->isEmpty() || NavigationItem::where('key', $selfKey)->exists()) {
                continue;
            }

            $opensParent = $tabs->contains(fn (NavigationItem $tab) => $tab->route === $parent->route
                && $tab->table_schema_uid === $parent->table_schema_uid
                && $tab->url === $parent->url
                && ($tab->params ?? []) == ($parent->params ?? []));

            if ($opensParent) {
                continue;
            }

            NavigationItem::create([
                'uid' => (string) Str::ulid(),
                'ref_module' => $module->id,
                'ref_parent' => $parent->id,
                'key' => $selfKey,
                'label' => $parent->label, // shown from the parent; kept for reading the table
                'sort' => (int) $tabs->min('sort') - 10,
                'status' => 'active',
                'source' => NavigationItem::SOURCE_SYSTEM,
            ]);
        }
    }

    /** @param list<string> $legacyOrder the old screen's saved order ("route::schemaUid") */
    private function syncLevel(Module $module, array $entries, ?NavigationItem $parent, string $prefix, array $legacyOrder, array &$seen): void
    {
        foreach ($entries as $index => $entry) {
            if (! is_array($entry) || empty($entry['label'])) {
                continue;
            }

            $key = $prefix.'.'.(isset($entry['key']) ? Str::slug((string) $entry['key'], '-') : Str::slug((string) $entry['label']));
            $seen[] = $key;

            $legacyKey = ($entry['route'] ?? '').'::'.($entry['default-table-schema-uid'] ?? 'none');
            $legacyPosition = $parent === null ? array_search($legacyKey, $legacyOrder, true) : false;
            $sort = ($legacyPosition !== false ? $legacyPosition : count($legacyOrder) + $index) * 10 + 10;

            $attributes = [
                'ref_module' => $module->id,
                'ref_parent' => $parent?->id,
                'label' => (string) $entry['label'],
                'icon' => $entry['icon'] ?? null,
                'route' => $entry['route'] ?? null,
                'params' => isset($entry['params']) && is_array($entry['params']) ? $entry['params'] : null,
                'table_schema_uid' => $entry['default-table-schema-uid'] ?? null,
                'url' => $entry['url'] ?? null,
                'permission' => $entry['permission'] ?? null,
                'sort' => $sort,
                'status' => 'active',
            ];

            $item = NavigationItem::where('key', $key)->first();

            if (! $item) {
                $item = NavigationItem::create($attributes + [
                    'uid' => (string) Str::ulid(),
                    'key' => $key,
                    'source' => NavigationItem::SOURCE_MODULE,
                ]);
            } elseif (! $item->customized) {
                $item->update($attributes);
            }

            // Extra settings declared by the module ("meta" => [key => value]).
            if (! $item->customized && is_array($entry['meta'] ?? null)) {
                foreach ($entry['meta'] as $metaKey => $metaValue) {
                    $item->setMeta((string) $metaKey, $metaValue);
                }
            }

            $this->syncLevel($module, is_array($entry['children'] ?? null) ? array_values($entry['children']) : [], $item, $key, [], $seen);
        }
    }

    /** The sub-menu order saved by the old navigation screen (navigations "{module}_sub_menu"). */
    private function legacyOrder(Module $module): array
    {
        if (! Schema::hasTable('navigations') || ! Schema::hasTable('navigation_metas')) {
            return [];
        }

        $navigationId = DB::table('navigations')->where('name', $module->name.'_sub_menu')->value('id');
        $order = $navigationId
            ? json_decode((string) DB::table('navigation_metas')->where('ref_parent', $navigationId)->where('meta_key', 'navigation_order')->value('meta_value'), true)
            : [];

        return is_array($order) ? array_values(array_filter($order, 'is_string')) : [];
    }
}
