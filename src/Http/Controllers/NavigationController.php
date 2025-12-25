<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Iquesters\Foundation\Models\Navigation;
use Iquesters\Foundation\Models\Module;

class NavigationController extends Controller
{
    /**
     * Show navigation ordering UI
     * Auto-syncs navigation_order with modules
     */
    public function index()
    {
        try {
            // Fetch active modules
            $modules = Module::active()->orderBy('id')->get();
            $moduleIds = $modules->pluck('id')->values()->toArray();

            // Get or create primary navigation
            $navigation = Navigation::firstOrCreate(
                ['name' => 'primary_navigation'],
                [
                    'uid' => (string) Str::ulid(),
                    'status' => 'active',
                    'created_by' => auth()->id() ?? 0,
                ]
            );

            // Read saved order
            $savedOrder = json_decode(
                $navigation->getMeta('navigation_order') ?? '[]',
                true
            );

            $savedOrder = is_array($savedOrder) ? $savedOrder : [];

            // Remove deleted modules
            $savedOrder = array_values(
                array_intersect($savedOrder, $moduleIds)
            );

            // Append newly added modules
            $newModules = array_diff($moduleIds, $savedOrder);

            // Final order
            $finalOrder = array_merge($savedOrder, $newModules);

            // Auto-save if needed
            if ($finalOrder !== $savedOrder || empty($savedOrder)) {
                $userId = auth()->id() ?? 0;

                $navigation->metas()->updateOrCreate(
                    ['meta_key' => 'navigation_order'],
                    [
                        'meta_value' => json_encode($finalOrder),
                        'status' => 'active',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                );
            }

            // Ordered modules (right panel)
            $orderedModules = collect($finalOrder)
                ->map(fn ($id) => $modules->firstWhere('id', $id))
                ->filter()
                ->values();

            // Available modules (left panel)
            $availableModules = $modules->reject(
                fn ($m) => in_array($m->id, $finalOrder)
            )->values();

            return view('foundation::navigation.index', compact(
                'navigation',
                'availableModules',
                'orderedModules'
            ));

        } catch (\Exception $e) {
            Log::error('Navigation index error', [
                'error' => $e->getMessage()
            ]);

            abort(500, 'Unable to load navigation');
        }
    }

    /**
     * Save navigation order (FORM submit)
     */
    public function saveOrder(Request $request)
    {
        try {
            $order = json_decode($request->order, true);

            Log::debug('Navigation save order', ['order' => $order]);

            if (!is_array($order)) {
                return back()->withErrors('Invalid navigation order');
            }

            $userId = auth()->id() ?? 0;

            // Get or create primary navigation
            $navigation = Navigation::firstOrCreate(
                ['name' => 'primary_navigation'],
                [
                    'uid' => (string) Str::ulid(),
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );

            // Save navigation order in meta with proper fields
            $navigation->metas()->updateOrCreate(
                ['meta_key' => 'navigation_order'],
                [
                    'meta_value' => json_encode(array_map('intval', $order)), // ensure numbers
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );

            return back()->with('success', 'Navigation order saved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to save navigation order', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return back()->with('error', 'Failed to save navigation order. Please try again.');
        }
    }

}