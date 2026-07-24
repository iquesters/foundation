<?php

namespace Iquesters\Foundation\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Iquesters\Foundation\Models\Module;
use Iquesters\Foundation\Models\Navigation;

class NavigationLayoutService
{
    public const MODULE_NAVIGATION_NAME = 'module_navigation';
    public const FOUNDATION_NAVIGATION_NAME = 'foundation_navigation';
    public const NAVIGATION_SECTION_SIDEBAR = 'sidebar';
    public const NAVIGATION_SECTION_MINIBAR = 'minibar';
    public const NAVIGATION_SECTION_TABS = 'tabs';

    public function getNavigationRows(): array
    {
        $this->syncFoundationNavigationStructure();

        return [
            'module_navigation' => $this->ensureModuleNavigation(),
            'foundation_navigation' => $this->ensureFoundationNavigation(),
        ];
    }

    public function getNavigationEditorPayload(): array
    {
        $rows = $this->getNavigationRows();

        return [
            'module_navigation' => $this->groupNavigationItems($rows['module_navigation']['items'] ?? []),
            'foundation_navigation' => $this->groupNavigationItems($rows['foundation_navigation']['items'] ?? []),
            'sections' => [
                self::NAVIGATION_SECTION_SIDEBAR,
                self::NAVIGATION_SECTION_MINIBAR,
                self::NAVIGATION_SECTION_TABS,
            ],
        ];
    }

    public function getRenderedNavigationPayload(): array
    {
        return [
            'module_navigation' => $this->groupNavigationItems($this->getNavigationItems('module_navigation')),
            'foundation_navigation' => $this->groupNavigationItems($this->getNavigationItems('foundation_navigation')),
        ];
    }

    public function getTabsForCurrentRoute(?string $routeName = null): array
    {
        $routeName ??= request()->route()?->getName();
        $currentPath = trim(parse_url((string) request()->getRequestUri(), PHP_URL_PATH) ?? '', '/');

        if (!$routeName) {
            return [];
        }

        $foundationModule = Module::where('name', 'foundation')->first();
        if (!$foundationModule) {
            return [];
        }

        $tabItems = Navigation::query()
            ->where('ref_parent', $foundationModule->id)
            ->with(['metas' => function ($query) {
                $query->where('meta_key', 'navigation_items');
            }])
            ->get()
            ->flatMap(function (Navigation $navigation) {
                $items = json_decode($navigation->getMeta('navigation_items') ?? '[]', true);
                $items = is_array($items) ? $items : [];

                return collect($items)
                    ->filter(function (array $item) {
                        $placement = $item['placement'] ?? $item['section'] ?? null;
                        return $placement === self::NAVIGATION_SECTION_TABS;
                    })
                    ->map(function (array $item) {
                        return [
                            'route' => $item['route'] ?? '#',
                            'params' => $item['params'] ?? [],
                            'icon' => $item['icon'] ?? null,
                            'label' => $item['label'] ?? '',
                            'parent_id' => $item['parent_id'] ?? null,
                            'family_route' => $item['family_route'] ?? ($item['route'] ?? null),
                            'sidebar_route' => $item['sidebar_route'] ?? null,
                            'sort_order' => $item['sort_order'] ?? PHP_INT_MAX,
                            'visible' => $item['visible'] ?? true,
                    'enabled' => $item['enabled'] ?? true,
                            'locked' => $item['locked'] ?? false,
                        ];
                    });
            })
            ->sortBy(fn ($item) => is_numeric($item['sort_order'] ?? null) ? (int) $item['sort_order'] : PHP_INT_MAX)
            ->values()
            ->all();

        if (empty($tabItems)) {
            return [];
        }

        $normalizeKey = function ($value): ?string {
            if (!is_string($value) && !is_numeric($value)) {
                return null;
            }

            $value = trim((string) $value);
            if ($value === '') {
                return null;
            }

            return trim(preg_replace('/^foundation[-_]/', '', $value), '-_');
        };

        $matchedTabs = collect($tabItems)->filter(function (array $item) use ($routeName, $currentPath) {
            $route = $item['route'] ?? null;
            if (!$route || !($item['visible'] ?? true) || !($item['enabled'] ?? true)) {
                return false;
            }

            if ($route === $routeName) {
                return true;
            }

            try {
                $resolvedPath = trim(parse_url(route($route, $item['params'] ?? []), PHP_URL_PATH) ?? '', '/');
                return $resolvedPath !== '' && $resolvedPath === $currentPath;
            } catch (\Throwable $e) {
                return false;
            }
        })->values();

        if ($matchedTabs->isEmpty()) {
            return [];
        }

        $parentKeys = $matchedTabs->pluck('parent_id')
            ->flatMap(function ($value) use ($normalizeKey) {
                return array_filter([
                    $value,
                    $normalizeKey($value),
                ]);
            })
            ->filter()
            ->unique()
            ->values();

        if ($parentKeys->isEmpty()) {
            return $matchedTabs->all();
        }

        $filteredTabs = collect($tabItems)
            ->filter(function (array $item) use ($parentKeys, $normalizeKey) {
                if (!($item['visible'] ?? true) || !($item['enabled'] ?? true)) {
                    return false;
                }

                $itemParent = $item['parent_id'] ?? null;
                return in_array($itemParent, $parentKeys->all(), true)
                    || in_array($normalizeKey($itemParent), $parentKeys->all(), true);
            })
            ->sortBy(fn ($item) => is_numeric($item['sort_order'] ?? null) ? (int) $item['sort_order'] : PHP_INT_MAX)
            ->values()
            ->all();

        return $filteredTabs;
    }

    public function getModuleNavigationByUid(string $moduleUid): ?array
    {
        $module = Module::where('uid', $moduleUid)->first();

        if (!$module) {
            return null;
        }

        return [
            'module_uid' => $module->uid,
            'module_name' => $module->name,
            'label' => $this->moduleLabel($module->name),
            'icon' => $this->moduleIconFromModule($module),
        ];
    }

    public function saveNavigationRows(?array $moduleNavigation, ?array $foundationNavigation): void
    {
        $userId = auth()->id() ?? 0;

        $moduleRow = $this->persistNavigationRow(
            self::MODULE_NAVIGATION_NAME,
            null,
            $this->normalizeModuleNavigation($moduleNavigation),
            $userId
        );

        $foundationRow = $this->persistNavigationRow(
            self::FOUNDATION_NAVIGATION_NAME,
            null,
            $this->normalizeFoundationNavigation($foundationNavigation),
            $userId
        );

        $this->syncModuleNavigationLinks($moduleRow);
        $this->syncFoundationNavigationLinks($foundationRow);
    }

    protected function ensureModuleNavigation(): array
    {
        $navigation = $this->resolveNavigation(self::MODULE_NAVIGATION_NAME);
        $items = $navigation->getMeta('navigation_items');

        if (!$items) {
            $items = $this->defaultModuleNavigationItems();
            $this->persistNavigationRow(
                self::MODULE_NAVIGATION_NAME,
                null,
                $items,
                auth()->id() ?? 0
            );
        } else {
            $items = $this->refreshModuleNavigationItems((string) $items);
        }

        return [
            'navigation' => $navigation->fresh(['metas']),
            'items' => json_decode($items, true) ?: [],
        ];
    }

    protected function ensureFoundationNavigation(): array
    {
        $this->syncFoundationNavigationStructure();

        $navigation = $this->resolveNavigation(self::FOUNDATION_NAVIGATION_NAME);
        $items = $navigation->getMeta('navigation_items');

        if (!$items) {
            $items = $this->defaultFoundationNavigationItems();
            $this->persistNavigationRow(
                self::FOUNDATION_NAVIGATION_NAME,
                null,
                $items,
                auth()->id() ?? 0
            );
        } else {
            $items = $this->refreshFoundationNavigationItems((string) $items);
        }

        return [
            'navigation' => $navigation->fresh(['metas']),
            'items' => json_decode($items, true) ?: [],
        ];
    }

    protected function getNavigationItems(string $name): array
    {
        $navigation = $this->resolveNavigation($name);
        $items = $navigation->getMeta('navigation_items');

        if (!$items) {
            return [];
        }

        return json_decode($items, true) ?: [];
    }

    protected function resolveSidebarNavigationForRoute(string $routeName): ?Navigation
    {
        $sidebarRows = Navigation::query()
            ->where('name', 'not like', 'foundation\\_%')
            ->orderBy('id')
            ->get();

        foreach ($sidebarRows as $navigation) {
            $item = $this->firstNavigationItem($navigation);
            if (!$item) {
                continue;
            }

            if (($item['route'] ?? null) === $routeName) {
                $placement = $item['placement'] ?? $item['section'] ?? self::NAVIGATION_SECTION_SIDEBAR;

                if ($placement === self::NAVIGATION_SECTION_SIDEBAR) {
                    return $navigation;
                }
            }
        }

        foreach ($sidebarRows as $navigation) {
            $item = $this->firstNavigationItem($navigation);
            if (!$item) {
                continue;
            }

            if (($item['route'] ?? null) !== $routeName) {
                continue;
            }

            $placement = $item['placement'] ?? $item['section'] ?? self::NAVIGATION_SECTION_SIDEBAR;
            if ($placement !== self::NAVIGATION_SECTION_TABS) {
                continue;
            }

            if ($navigation->ref_parent) {
                $parentNavigation = Navigation::find($navigation->ref_parent);
                if ($parentNavigation) {
                    return $parentNavigation;
                }
            }
        }

        return null;
    }

    protected function firstNavigationItem(Navigation $navigation): ?array
    {
        $items = $navigation->getMeta('navigation_items');
        if (!$items) {
            return null;
        }

        $decoded = json_decode($items, true);
        if (!is_array($decoded) || empty($decoded)) {
            return null;
        }

        return $decoded[0];
    }

    protected function sidebarRouteFromNavigation(Navigation $navigation): ?string
    {
        $item = $this->firstNavigationItem($navigation);

        return $item['route'] ?? null;
    }

    protected function refreshModuleNavigationItems(string $items): string
    {
        $decoded = json_decode($items, true);

        if (!is_array($decoded)) {
            return $items;
        }

        $modules = Module::active()->orderBy('id')->get()->keyBy('uid');
        $dirty = false;

        $normalized = collect($decoded)->map(function (array $item) use ($modules, &$dirty) {
            $moduleUid = $item['module_uid'] ?? $item['target_module_uid'] ?? null;
            $module = $moduleUid ? $modules->get($moduleUid) : null;

            if ($module) {
                $icon = $this->moduleIconFromModule($module);

                if (($item['icon'] ?? null) !== $icon) {
                    $item['icon'] = $icon;
                    $dirty = true;
                }

                $item['label'] = $item['label'] ?? $this->moduleLabel($module->name);
                $item['original_label'] = $item['original_label'] ?? $this->moduleLabel($module->name);
                $item['module_name'] = $item['module_name'] ?? $module->name;
                $item['module_uid'] = $item['module_uid'] ?? $module->uid;
                $item['target_module_uid'] = $item['target_module_uid'] ?? $module->uid;
            }

            return $item;
        })->all();

        if ($dirty) {
            $navigation = Navigation::where('name', self::MODULE_NAVIGATION_NAME)->first();

            if ($navigation) {
                $navigation->metas()->updateOrCreate(
                    ['meta_key' => 'navigation_items'],
                    [
                        'meta_value' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'status' => 'active',
                        'updated_by' => auth()->id() ?? 0,
                    ]
                );
            }
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function refreshFoundationNavigationItems(string $items): string
    {
        $decoded = json_decode($items, true);

        if (!is_array($decoded)) {
            return $items;
        }

        $foundationModule = Module::where('name', 'foundation')->first();
        if (!$foundationModule) {
            return $items;
        }

        $foundationUid = $foundationModule->uid;
        $dirty = false;

        $normalized = collect($decoded)->map(function (array $item) use ($foundationUid, $foundationModule, &$dirty) {
            if (($item['module_uid'] ?? null) !== $foundationUid) {
                $item['module_uid'] = $foundationUid;
                $dirty = true;
            }

            if (($item['target_module_uid'] ?? null) !== $foundationUid) {
                $item['target_module_uid'] = $foundationUid;
                $dirty = true;
            }

            $item['module_name'] = $item['module_name'] ?? $foundationModule->name;

            return $item;
        })->all();

        if ($dirty) {
            $navigation = Navigation::where('name', self::FOUNDATION_NAVIGATION_NAME)->first();

            if ($navigation) {
                $navigation->metas()->updateOrCreate(
                    ['meta_key' => 'navigation_items'],
                    [
                        'meta_value' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'status' => 'active',
                        'updated_by' => auth()->id() ?? 0,
                    ]
                );
            }
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function resolveNavigation(string $name): Navigation
    {
        return Navigation::firstOrCreate(
            ['name' => $name],
            [
                'uid' => (string) Str::ulid(),
                'status' => 'active',
                'created_by' => auth()->id() ?? 0,
            ]
        );
    }

    protected function persistNavigationRow(string $name, ?int $refParent, array|string $items, int $userId): Navigation
    {
        $navigation = Navigation::firstOrCreate(
            ['name' => $name],
            [
                'uid' => (string) Str::ulid(),
                'ref_parent' => $refParent,
                'status' => 'active',
                'created_by' => $userId,
            ]
        );

        $navigation->metas()->updateOrCreate(
            ['meta_key' => 'navigation_items'],
            [
                'meta_value' => is_string($items) ? $items : json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status' => 'active',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );

        return $navigation;
    }

    protected function syncModuleNavigationLinks(Navigation $navigation): void
    {
        $modules = Module::active()->orderBy('id')->get();

        foreach ($modules as $index => $module) {
            $moduleName = $module->name;

            DB::table('module_has_navigations')->updateOrInsert(
                [
                    'module_id' => $module->id,
                    'navigation_id' => $navigation->id,
                ],
                [
                    'label' => $this->moduleLabel($module->name),
                    'icon' => $this->moduleIconFromModule($module),
                    'sort_order' => ($index + 1) * 10,
                    'visible' => true,
                    'enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function syncFoundationNavigationLinks(Navigation $navigation): void
    {
        $foundationModule = Module::where('name', 'foundation')->first();

        if (!$foundationModule) {
            return;
        }

        $foundationItems = $this->foundationSidebarItems();

        foreach ($foundationItems as $index => $item) {
            DB::table('module_has_navigations')->updateOrInsert(
                [
                    'module_id' => $foundationModule->id,
                    'navigation_id' => $navigation->id,
                    'label' => $item['label'],
                ],
                [
                    'icon' => $item['icon'],
                    'sort_order' => ($index + 1) * 10,
                    'visible' => true,
                    'enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function defaultModuleNavigationItems(): string
    {
        $modules = Module::active()
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (Module $module, int $index) {
                return [
                    'id' => 'module-' . $module->name,
                    'label' => $this->moduleLabel($module->name),
                    'original_label' => $this->moduleLabel($module->name),
                    'slug' => Str::slug($module->name),
                    'icon' => $this->moduleIconFromModule($module),
                    'module_uid' => $module->uid,
                    'module_name' => $module->name,
                    'target_module_uid' => $module->uid,
                    'sort_order' => ($index + 1) * 10,
                    'visible' => true,
                    'enabled' => true,
                    'locked' => false,
                    'source' => [
                        'table' => 'modules',
                        'meta_key' => 'module_order',
                    ],
                ];
            })
            ->all();

        return json_encode($modules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function defaultFoundationNavigationItems(): string
    {
        $moduleItems = $this->foundationSidebarItems();

        return json_encode($moduleItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function groupNavigationItems(array $items): array
    {
        $grouped = [
            self::NAVIGATION_SECTION_SIDEBAR => [],
            self::NAVIGATION_SECTION_MINIBAR => [],
            self::NAVIGATION_SECTION_TABS => [],
        ];

        foreach ($items as $index => $item) {
            $section = $item['placement'] ?? $item['section'] ?? self::NAVIGATION_SECTION_SIDEBAR;
            if (!array_key_exists($section, $grouped)) {
                $section = self::NAVIGATION_SECTION_SIDEBAR;
            }

            $item['placement'] = $section;
            $item['sort_order'] = $item['sort_order'] ?? (($index + 1) * 10);
            $grouped[$section][] = $item;
        }

        foreach ($grouped as &$sectionItems) {
            usort($sectionItems, fn ($left, $right) => ($left['sort_order'] ?? 0) <=> ($right['sort_order'] ?? 0));
        }

        return $grouped;
    }

    protected function foundationSidebarItems(): array
    {
        $foundationModule = Module::where('name', 'foundation')->first();

        if (!$foundationModule) {
            return [];
        }

        return [
            [
                'id' => 'foundation-master-datas',
                'label' => 'All Masterdatas',
                'original_label' => 'All Masterdatas',
                'slug' => 'all-masterdatas',
                'placement' => self::NAVIGATION_SECTION_SIDEBAR,
                'route' => 'master-data.index',
                'module_uid' => $foundationModule->uid,
                'target_module_uid' => $foundationModule->uid,
                'icon' => 'fas fa-list-ul',
                'sort_order' => 10,
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'source' => [
                    'table' => 'module_metas',
                    'meta_key' => 'module_sidebar_menu',
                ],
            ],
            [
                'id' => 'foundation-modules',
                'label' => 'Modules',
                'original_label' => 'Modules',
                'slug' => 'modules',
                'route' => 'modules.assign-to-role',
                'module_uid' => $foundationModule->uid,
                'target_module_uid' => $foundationModule->uid,
                'icon' => 'fas fa-cubes',
                'sort_order' => 20,
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'source' => [
                    'table' => 'module_metas',
                    'meta_key' => 'module_sidebar_menu',
                ],
            ],
            [
                'id' => 'foundation-entities',
                'label' => 'Entities',
                'original_label' => 'Entities',
                'slug' => 'entities',
                'route' => 'entities.index',
                'module_uid' => $foundationModule->uid,
                'target_module_uid' => $foundationModule->uid,
                'icon' => 'fas fa-database',
                'sort_order' => 30,
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'source' => [
                    'table' => 'module_metas',
                    'meta_key' => 'module_sidebar_menu',
                ],
            ],
            [
                'id' => 'foundation-queue-old',
                'label' => 'Queue OLD',
                'original_label' => 'Queue OLD',
                'slug' => 'queue-old',
                'route' => 'smart-messenger.queue-management',
                'module_uid' => $foundationModule->uid,
                'target_module_uid' => $foundationModule->uid,
                'icon' => 'fas fa-layer-group',
                'sort_order' => 40,
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'source' => [
                    'table' => 'module_metas',
                    'meta_key' => 'module_sidebar_menu',
                ],
            ],
            [
                'id' => 'foundation-queue',
                'label' => 'Queue',
                'original_label' => 'Queue',
                'slug' => 'queue',
                'route' => 'ui.list',
                'module_uid' => $foundationModule->uid,
                'target_module_uid' => $foundationModule->uid,
                'icon' => 'fas fa-tasks',
                'sort_order' => 50,
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'source' => [
                    'table' => 'module_metas',
                    'meta_key' => 'module_sidebar_menu',
                ],
            ],
            [
                'id' => 'foundation-job-old',
                'label' => 'Job OLD',
                'original_label' => 'Job OLD',
                'slug' => 'job-old',
                'route' => 'jobs.index',
                'module_uid' => $foundationModule->uid,
                'target_module_uid' => $foundationModule->uid,
                'icon' => 'fas fa-sync-alt',
                'sort_order' => 60,
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'source' => [
                    'table' => 'module_metas',
                    'meta_key' => 'module_sidebar_menu',
                ],
            ],
            [
                'id' => 'foundation-jobs',
                'label' => 'Jobs',
                'original_label' => 'Jobs',
                'slug' => 'jobs',
                'route' => 'ui.list',
                'module_uid' => $foundationModule->uid,
                'target_module_uid' => $foundationModule->uid,
                'icon' => 'fas fa-tasks',
                'sort_order' => 70,
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'source' => [
                    'table' => 'module_metas',
                    'meta_key' => 'module_sidebar_menu',
                ],
            ],
            [
                'id' => 'foundation-failed-jobs',
                'label' => 'Failed Jobs',
                'original_label' => 'Failed Jobs',
                'slug' => 'failed-jobs',
                'route' => 'ui.list',
                'module_uid' => $foundationModule->uid,
                'target_module_uid' => $foundationModule->uid,
                'icon' => 'fas fa-times-circle',
                'sort_order' => 80,
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'source' => [
                    'table' => 'module_metas',
                    'meta_key' => 'module_sidebar_menu',
                ],
            ],
            [
                'id' => 'foundation-completed-jobs',
                'label' => 'Completed Jobs',
                'original_label' => 'Completed Jobs',
                'slug' => 'completed-jobs',
                'route' => 'ui.list',
                'module_uid' => $foundationModule->uid,
                'target_module_uid' => $foundationModule->uid,
                'icon' => 'fas fa-check-circle',
                'sort_order' => 90,
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'source' => [
                    'table' => 'module_metas',
                    'meta_key' => 'module_sidebar_menu',
                ],
            ],
        ];
    }

    protected function syncFoundationNavigationStructure(): void
    {
        $foundationModule = Module::where('name', 'foundation')->first();
        if (!$foundationModule) {
            return;
        }

        $sidebarDefinitions = $this->foundationSidebarItems();
        $tabDefinitions = $this->foundationTabDefinitions();

        foreach ($sidebarDefinitions as $sidebar) {
            $sidebarNavigation = Navigation::firstOrCreate(
                ['name' => $sidebar['id']],
                [
                    'uid' => (string) Str::ulid(),
                    'ref_parent' => $foundationModule->id,
                    'status' => 'active',
                    'created_by' => auth()->id() ?? 0,
                ]
            );

            $sidebarPayload = [[
                'id' => $sidebar['id'],
                'label' => $sidebar['label'],
                'original_label' => $sidebar['original_label'],
                'slug' => $sidebar['slug'],
                'route' => $sidebar['route'] ?? null,
                'icon' => $sidebar['icon'],
                'placement' => self::NAVIGATION_SECTION_SIDEBAR,
                'module_uid' => $foundationModule->uid,
                'target_module_uid' => $foundationModule->uid,
                'module_name' => $foundationModule->name,
                'sort_order' => $sidebar['sort_order'],
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'source' => [
                    'table' => 'module_metas',
                    'meta_key' => 'module_sidebar_menu',
                ],
            ]];

            if (!$sidebarNavigation->getMeta('navigation_items')) {
                $sidebarNavigation->metas()->updateOrCreate(
                    ['meta_key' => 'navigation_items'],
                    [
                        'meta_value' => json_encode($sidebarPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'status' => 'active',
                        'created_by' => auth()->id() ?? 0,
                        'updated_by' => auth()->id() ?? 0,
                    ]
                );
            }

            DB::table('module_has_navigations')->updateOrInsert(
                [
                    'module_id' => $foundationModule->id,
                    'navigation_id' => $sidebarNavigation->id,
                ],
                [
                    'label' => $sidebar['label'],
                    'icon' => $sidebar['icon'],
                    'sort_order' => $sidebar['sort_order'],
                    'visible' => 1,
                    'enabled' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $tabs = $tabDefinitions[$sidebar['id']] ?? [];

            foreach ($tabs as $index => $tab) {
                $tabNavigation = Navigation::firstOrCreate(
                    ['name' => $tab['name']],
                    [
                        'uid' => (string) Str::ulid(),
                        'ref_parent' => $foundationModule->id,
                        'status' => 'active',
                        'created_by' => auth()->id() ?? 0,
                    ]
                );

                $tabPayload = [[
                    'id' => $tab['id'],
                    'label' => $tab['label'],
                    'original_label' => $tab['original_label'],
                    'slug' => $tab['slug'],
                    'route' => $tab['route'],
                    'icon' => $tab['icon'],
                    'placement' => self::NAVIGATION_SECTION_TABS,
                    'parent_id' => $sidebar['id'],
                    'module_uid' => $foundationModule->uid,
                    'target_module_uid' => $foundationModule->uid,
                    'module_name' => $foundationModule->name,
                    'sort_order' => ($index + 1) * 10,
                    'visible' => true,
                    'enabled' => true,
                    'locked' => false,
                    'source' => [
                        'table' => 'module_metas',
                        'meta_key' => 'module_sidebar_menu',
                    ],
                ]];

                if (!$tabNavigation->getMeta('navigation_items')) {
                    $tabNavigation->metas()->updateOrCreate(
                        ['meta_key' => 'navigation_items'],
                        [
                            'meta_value' => json_encode($tabPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'status' => 'active',
                            'created_by' => auth()->id() ?? 0,
                            'updated_by' => auth()->id() ?? 0,
                        ]
                    );
                }

                DB::table('module_has_navigations')->updateOrInsert(
                    [
                        'module_id' => $foundationModule->id,
                        'navigation_id' => $tabNavigation->id,
                    ],
                    [
                        'label' => $tab['label'],
                        'icon' => $tab['icon'],
                        'sort_order' => ($index + 1) * 10,
                        'visible' => 1,
                        'enabled' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    protected function foundationTabDefinitions(): array
    {
        return [
            'foundation-master-datas' => [
                [
                    'name' => 'foundation-master-datas-tab',
                    'id' => 'masterdatas-tab',
                    'label' => 'Master Data',
                    'original_label' => 'Master Data',
                    'slug' => 'master-data',
                    'route' => 'master-data.index',
                    'icon' => 'fas fa-fw fa-database',
                ],
            ],
            'foundation-entities' => [
                [
                    'name' => 'foundation-entities-tab',
                    'id' => 'entities-tab',
                    'label' => 'Entity',
                    'original_label' => 'Entity',
                    'slug' => 'entity',
                    'route' => 'entities.index',
                    'icon' => 'fas fa-fw fa-cube',
                ],
            ],
            'foundation-job-old' => [
                [
                    'name' => 'foundation-job-old-current',
                    'id' => 'job-old-current-tab',
                    'label' => 'Current',
                    'original_label' => 'Current',
                    'slug' => 'current',
                    'route' => 'jobs.index',
                    'icon' => 'fas fa-fw fa-stream',
                ],
                [
                    'name' => 'foundation-job-old-completed',
                    'id' => 'job-old-completed-tab',
                    'label' => 'Completed',
                    'original_label' => 'Completed',
                    'slug' => 'completed',
                    'route' => 'jobs.completed',
                    'icon' => 'fas fa-fw fa-check-circle',
                ],
                [
                    'name' => 'foundation-job-old-failed',
                    'id' => 'job-old-failed-tab',
                    'label' => 'Failed',
                    'original_label' => 'Failed',
                    'slug' => 'failed',
                    'route' => 'jobs.failed',
                    'icon' => 'fas fa-fw fa-times-circle',
                ],
                [
                    'name' => 'foundation-job-old-history',
                    'id' => 'job-old-history-tab',
                    'label' => '',
                    'original_label' => 'History',
                    'slug' => 'history',
                    'route' => '#',
                    'icon' => 'fas fa-fw fa-clock-rotate-left',
                ],
            ],
        ];
    }

    protected function normalizeModuleNavigation(?array $items): array
    {
        $items = $items ?: json_decode($this->defaultModuleNavigationItems(), true);

        return collect($items)->values()->map(function ($item, $index) {
            return array_merge([
                'id' => 'module-item-' . ($index + 1),
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'placement' => self::NAVIGATION_SECTION_SIDEBAR,
            ], Arr::only($item, [
                'id', 'label', 'original_label', 'slug', 'icon', 'visible', 'enabled', 'locked',
                'placement', 'route', 'type', 'module_uid', 'module_name', 'target_module_uid', 'parent_id',
            ]), [
                'sort_order' => (($index + 1) * 10),
            ]);
        })->all();
    }

    protected function normalizeFoundationNavigation(?array $items): array
    {
        $items = $items ?: json_decode($this->defaultFoundationNavigationItems(), true);

        return collect($items)->values()->map(function ($item, $index) {
            return array_merge([
                'id' => $item['id'] ?? 'foundation-item-' . ($index + 1),
                'visible' => true,
                'enabled' => true,
                'locked' => false,
                'placement' => $item['placement'] ?? self::NAVIGATION_SECTION_SIDEBAR,
            ], $item, [
                'sort_order' => (($index + 1) * 10),
            ]);
        })->all();
    }

    protected function moduleLabel(string $moduleName): string
    {
        return Str::of($moduleName)->replace('-', ' ')->title()->toString();
    }

    protected function moduleIcon(string $moduleName): string
    {
        return match ($moduleName) {
            'user-interface' => 'fas fa-palette',
            'user-management' => 'fas fa-users-cog',
            default => 'fas fa-folder',
        };
    }

    protected function moduleIconFromModule(Module $module): string
    {
        return $module->getMeta('module_icon') ?: $this->moduleIcon($module->name);
    }

    public function tabNavigationTemplate(string $sidebarNavigationName): array
    {
        return match ($sidebarNavigationName) {
            'masterdatas' => [
                [
                    'id' => 'masterdatas-tab',
                    'label' => 'Master Data',
                    'original_label' => 'Master Data',
                    'slug' => 'master-data',
                    'route' => 'master-data.index',
                    'icon' => 'fas fa-fw fa-database',
                    'placement' => self::NAVIGATION_SECTION_TABS,
                    'sort_order' => 10,
                    'visible' => true,
                    'enabled' => true,
                    'locked' => false,
                ],
            ],
            'entities' => [
                [
                    'id' => 'entities-tab',
                    'label' => 'Entity',
                    'original_label' => 'Entity',
                    'slug' => 'entity',
                    'route' => 'entities.index',
                    'icon' => 'fas fa-fw fa-cube',
                    'placement' => self::NAVIGATION_SECTION_TABS,
                    'sort_order' => 10,
                    'visible' => true,
                    'enabled' => true,
                    'locked' => false,
                ],
            ],
            'job_old' => [
                [
                    'id' => 'job-old-current-tab',
                    'label' => 'Current',
                    'original_label' => 'Current',
                    'slug' => 'current',
                    'route' => 'jobs.index',
                    'icon' => 'fas fa-fw fa-stream',
                    'placement' => self::NAVIGATION_SECTION_TABS,
                    'sort_order' => 10,
                    'visible' => true,
                    'enabled' => true,
                    'locked' => false,
                ],
                [
                    'id' => 'job-old-completed-tab',
                    'label' => 'Completed',
                    'original_label' => 'Completed',
                    'slug' => 'completed',
                    'route' => 'jobs.completed',
                    'icon' => 'fas fa-fw fa-check-circle',
                    'placement' => self::NAVIGATION_SECTION_TABS,
                    'sort_order' => 20,
                    'visible' => true,
                    'enabled' => true,
                    'locked' => false,
                ],
                [
                    'id' => 'job-old-failed-tab',
                    'label' => 'Failed',
                    'original_label' => 'Failed',
                    'slug' => 'failed',
                    'route' => 'jobs.failed',
                    'icon' => 'fas fa-fw fa-times-circle',
                    'placement' => self::NAVIGATION_SECTION_TABS,
                    'sort_order' => 30,
                    'visible' => true,
                    'enabled' => true,
                    'locked' => false,
                ],
                [
                    'id' => 'job-old-history-tab',
                    'label' => '',
                    'original_label' => 'History',
                    'slug' => 'history',
                    'route' => '#',
                    'icon' => 'fas fa-fw fa-clock-rotate-left',
                    'placement' => self::NAVIGATION_SECTION_TABS,
                    'sort_order' => 40,
                    'visible' => true,
                    'enabled' => true,
                    'locked' => false,
                ],
            ],
            default => [],
        };
    }
}
