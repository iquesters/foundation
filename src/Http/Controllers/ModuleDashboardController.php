<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;
use Iquesters\Foundation\Models\Module;
use Iquesters\Foundation\Models\NavigationItem;

/**
 * A module's dashboard (/dashboard/{module}), the fixed first item of its
 * sidebar. By default it lists the module's pages; a module shows its own
 * view instead by setting the module meta "module_dashboard_view" (a view
 * name, given $module and $items).
 */
class ModuleDashboardController extends Controller
{
    public function show(string $module)
    {
        $module = Module::with('metas')->where('name', $module)->where('status', 'active')->firstOrFail();
        $user = auth()->user();

        abort_unless($user && ($module->isAccessibleByUser($user) || (method_exists($user, 'hasRole') && $user->hasRole('super-admin'))), 403);

        $items = NavigationItem::with(['children' => fn ($q) => $q->where('status', 'active')])
            ->where('ref_module', $module->id)
            ->whereNull('ref_parent')
            ->where('status', 'active')
            ->where('source', '<>', NavigationItem::SOURCE_SYSTEM)
            ->orderBy('sort')->orderBy('id')
            ->get()
            ->filter(fn (NavigationItem $item) => $item->visibleTo($user))
            ->values();

        $view = $module->getMeta('module_dashboard_view');

        return view(is_string($view) && View::exists($view) ? $view : 'foundation::modules.dashboard', compact('module', 'items'));
    }
}
