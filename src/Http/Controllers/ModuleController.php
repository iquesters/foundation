<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;
use Iquesters\Foundation\Models\Module;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ModuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $modules = Module::with('metas')->get();
        $roles = Role::all();
        return view('foundation::modules.index', compact('modules', 'roles'));
    }

    /**
     * Show form to assign modules to roles
     */
    public function assignToRole(Request $request)
    {
        $modules = Module::active()->get();
        $roles = Role::all();
        
        $selectedRoleId = $request->role_id;
        $selectedRole = $selectedRoleId ? Role::find($selectedRoleId) : null;
        
        return view('foundation::modules.assign-to-role', compact('modules', 'roles', 'selectedRole'));
    }

    /**
     * Assign modules to a role
     */
    public function updateRoleModules(Request $request, Role $role)
    {
        try {
            $request->validate([
                'modules' => ['nullable', 'array'],
                'modules.*' => ['exists:modules,id'],
            ]);

            // Get all modules
            $allModules = Module::all();
            
            // Update each module's role assignment
            foreach ($allModules as $module) {
                $assignedRoleIds = $module->getAssignedRoleIds();
                
                if (in_array($module->id, $request->modules ?? [])) {
                    // Add role to module if not already assigned
                    if (!in_array($role->id, $assignedRoleIds)) {
                        $assignedRoleIds[] = $role->id;
                        $module->assignRoles($assignedRoleIds);
                    }
                } else {
                    // Remove role from module if assigned
                    if (in_array($role->id, $assignedRoleIds)) {
                        $assignedRoleIds = array_diff($assignedRoleIds, [$role->id]);
                        $module->assignRoles(array_values($assignedRoleIds));
                    }
                }
            }

            Log::info('Modules assigned to role', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'modules' => $request->modules ?? [],
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('modules.assign-to-role', ['role_id' => $role->id])
                ->with('success', 'Modules assigned to role successfully');
                
        } catch (\Exception $e) {
            Log::error('Error assigning modules to role', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            
            return redirect()->back()->with('error', 'Failed to assign modules to role');
        }
    }

    /**
     * Get modules assigned to a specific role
     */
    public function getRoleModules(Role $role)
    {
        $modules = Module::all()->filter(function ($module) use ($role) {
            return $module->isAssignedToRole($role);
        });
        
        return response()->json($modules);
    }
}