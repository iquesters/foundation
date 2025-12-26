<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Iquesters\Foundation\Models\Navigation;
use Iquesters\Foundation\Models\Module;
use Iquesters\UserInterface\Models\TableSchema;

class NavigationController extends Controller
{
    public function index()
{
    try {
        Log::info('Fetching all navigation');

        $navigations = Navigation::with('metas')->get();

        Log::info('Displaying navigations', [
            'count' => $navigations->count()
        ]);

        // $tableSchema = TableSchema::where('slug', 'navigations-table')->first();
        Log::debug('Table Schema details', [$tableSchema]);
        if (!$tableSchema) {
            return redirect()
                ->back()
                ->with('error', 'Table schema not found.');
        }

        return redirect()->route('ui.list', [
            'table_schema_id' => 'navigations-table'
        ]);

        // OR if ui.list expects a view:
        // return view('ui.list', compact('tableSchema', 'navigations'));

    } catch (\Throwable $e) {
        Log::error('Error fetching navigation', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()
            ->back()
            ->with('error', 'An error occurred while fetching navigations.');
    }
}
    
    /**
     * Show navigation ordering UI
     * Auto-syncs primary navigation_order with modules
     */
    public function details()
    {
        try {
            $userId = auth()->id() ?? 0;

            /** ---------------- PRIMARY NAV ---------------- */
            $modules = Module::active()->orderBy('id')->get();
            $moduleIds = $modules->pluck('id')->values()->toArray();

            $primaryNav = Navigation::firstOrCreate(
                ['name' => 'primary_navigation'],
                [
                    'uid'        => (string) Str::ulid(),
                    'status'     => 'active',
                    'created_by' => $userId,
                ]
            );

            $savedOrder = json_decode(
                $primaryNav->getMeta('navigation_order') ?? '[]',
                true
            );

            $savedOrder = is_array($savedOrder) ? $savedOrder : [];
            $savedOrder = array_values(array_intersect($savedOrder, $moduleIds));

            $newModules = array_diff($moduleIds, $savedOrder);
            $finalOrder = array_merge($savedOrder, $newModules);

            if ($finalOrder !== $savedOrder || empty($savedOrder)) {
                $primaryNav->metas()->updateOrCreate(
                    ['meta_key' => 'navigation_order'],
                    [
                        'meta_value' => json_encode($finalOrder),
                        'status'     => 'active',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                );
            }

            $orderedModules = collect($finalOrder)
                ->map(fn ($id) => $modules->firstWhere('id', $id))
                ->filter()
                ->values();

            /** ---------------- SUB MENUS (AUTO SYNC) ---------------- */
            foreach ($modules as $module) {

                $submenuItems = json_decode(
                    $module->getMeta('module_sidebar_menu') ?? '[]',
                    true
                );

                if (!is_array($submenuItems) || empty($submenuItems)) {
                    continue;
                }

                $routes = collect($submenuItems)
                    ->pluck('route')
                    ->filter()
                    ->values()
                    ->toArray();

                if (empty($routes)) {
                    continue;
                }

                $submenuNav = Navigation::firstOrCreate(
                    [
                        'name' => $module->name . '_sub_menu',
                        'ref_parent' => $module->id
                    ],
                    [
                        'uid'        => (string) Str::ulid(),
                        'status'     => 'active',
                        'created_by' => $userId,
                    ]
                );

                $savedSubOrder = json_decode(
                    $submenuNav->getMeta('navigation_order') ?? '[]',
                    true
                );

                $savedSubOrder = is_array($savedSubOrder) ? $savedSubOrder : [];
                $savedSubOrder = array_values(array_intersect($savedSubOrder, $routes));

                $newRoutes = array_diff($routes, $savedSubOrder);
                $finalSubOrder = array_merge($savedSubOrder, $newRoutes);

                if ($finalSubOrder !== $savedSubOrder || empty($savedSubOrder)) {
                    $submenuNav->metas()->updateOrCreate(
                        ['meta_key' => 'navigation_order'],
                        [
                            'meta_value' => json_encode($finalSubOrder),
                            'status'     => 'active',
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]
                    );
                }
            }

            return view('foundation::navigation.details', compact(
                'orderedModules'
            ));

        } catch (\Throwable $e) {
            Log::error('Navigation index error', ['error' => $e->getMessage()]);
            abort(500, 'Unable to load navigation');
        }
    }

    /**
     * Load module submenu (stored in navigation table)
     */
    public function loadModuleSubMenu($moduleUid)
    {
        Log::info('loadModuleSubMenu started', [
            'module_uid' => $moduleUid
        ]);

        try {
            $module = Module::where('uid', $moduleUid)
                ->where('status', 'active')
                ->first();

            if (!$module) {
                Log::warning('Active module not found', [
                    'module_uid' => $moduleUid
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Module not found'
                ], 404);
            }

            Log::info('Module loaded', [
                'module_id' => $module->id,
                'module_name' => $module->name
            ]);

            // Read submenu items from module meta
            $submenuItems = json_decode(
                $module->getMeta('module_sidebar_menu') ?? '[]',
                true
            );

            $submenuItems = is_array($submenuItems) ? $submenuItems : [];

            Log::debug('Submenu meta loaded', [
                'count' => count($submenuItems)
            ]);

            // Unique routes
            $routes = collect($submenuItems)
                ->pluck('route')
                ->filter()
                ->values()
                ->toArray();

            Log::debug('Extracted submenu routes', [
                'routes' => $routes
            ]);

            $navigationName = $module->name . '_sub_menu';

            // Get or create submenu navigation
            $navigation = Navigation::firstOrCreate(
                ['name' => $navigationName],
                [
                    'uid'        => (string) Str::ulid(),
                    'status'     => 'active',
                    'created_by' => auth()->id() ?? 0,
                ]
            );

            Log::info('Navigation resolved', [
                'navigation_id' => $navigation->id,
                'navigation_name' => $navigationName
            ]);

            // Read saved order
            $savedOrder = json_decode(
                $navigation->getMeta('navigation_order') ?? '[]',
                true
            );

            $savedOrder = is_array($savedOrder) ? $savedOrder : [];

            Log::debug('Saved navigation order', [
                'saved_order' => $savedOrder
            ]);

            // Remove deleted submenu items
            $savedOrder = array_values(array_intersect($savedOrder, $routes));

            // Append new submenu items
            $newItems = array_diff($routes, $savedOrder);
            $finalOrder = array_merge($savedOrder, $newItems);

            if ($finalOrder !== $savedOrder || empty($savedOrder)) {
                $navigation->metas()->updateOrCreate(
                    ['meta_key' => 'navigation_order'],
                    [
                        'meta_value' => json_encode($finalOrder),
                        'status'     => 'active',
                        'created_by' => auth()->id() ?? 0,
                        'updated_by' => auth()->id() ?? 0,
                    ]
                );

                Log::info('Navigation order updated', [
                    'final_order' => $finalOrder
                ]);
            }

            // Build ordered submenu
            $orderedSubMenu = collect($finalOrder)
                ->map(fn ($route) =>
                    collect($submenuItems)->firstWhere('route', $route)
                )
                ->filter()
                ->values();

            Log::info('Submenu build completed', [
                'submenu_count' => $orderedSubMenu->count()
            ]);

            return response()->json([
                'success' => true,
                'submenu' => $orderedSubMenu,
            ]);

        } catch (\Throwable $e) {
            Log::error('Load submenu failed', [
                'module_uid' => $moduleUid,
                'exception'  => $e,
            ]);

            return response()->json([
                'success' => false
            ], 500);
        }
    }

    /**
     * Save navigation order (primary + sub menu)
     */
    public function saveOrder(Request $request)
    {
        try {
            $userId = auth()->id() ?? 0;

            /**
             * PRIMARY NAVIGATION
             */
            if ($request->filled('order')) {
                $order = json_decode($request->order, true);

                if (is_array($order)) {
                    $navigation = Navigation::firstOrCreate(
                        ['name' => 'primary_navigation'],
                        [
                            'uid'        => (string) Str::ulid(),
                            'status'     => 'active',
                            'created_by' => $userId,
                        ]
                    );

                    $navigation->metas()->updateOrCreate(
                        ['meta_key' => 'navigation_order'],
                        [
                            'meta_value' => json_encode(array_map('intval', $order)),
                            'status'     => 'active',
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]
                    );
                }
            }

            /**
             * MODULE SUB MENU
             */
            if ($request->filled('submenu_module_id') && $request->filled('submenu_order')) {

                $module = Module::find($request->submenu_module_id);

                if ($module) {
                    $navigationName = $module->name . '_sub_menu';
                    $order = json_decode($request->submenu_order, true);

                    if (is_array($order)) {
                        $navigation = Navigation::firstOrCreate(
                            ['name' => $navigationName],
                            [
                                'uid'        => (string) Str::ulid(),
                                'status'     => 'active',
                                'created_by' => $userId,
                            ]
                        );

                        $navigation->metas()->updateOrCreate(
                            ['meta_key' => 'navigation_order'],
                            [
                                'meta_value' => json_encode(array_values($order)),
                                'status'     => 'active',
                                'created_by' => $userId,
                                'updated_by' => $userId,
                            ]
                        );
                    }
                }
            }

            return back()->with('success', 'Navigation updated successfully');

        } catch (\Throwable $e) {
            Log::error('Save navigation failed', [
                'error' => $e->getMessage(),
                'user'  => auth()->id(),
            ]);

            return back()->with('error', 'Failed to save navigation');
        }
    }
}