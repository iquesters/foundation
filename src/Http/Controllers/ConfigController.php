<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Iquesters\Foundation\Models\Module;
use Illuminate\Support\Facades\Log;
use Iquesters\Foundation\Models\MasterData;

class ConfigController extends Controller
{
    /**
     * Show config index or module-specific configuration
     */
    public function index(Request $request, $moduleId = null)
    {
        try {
            $modules = Module::active()->get();
            $selectedModule = $moduleId ? Module::find($moduleId) : null;

            $configData = collect();

            if ($selectedModule) {
                $masterData = MasterData::where('key', $selectedModule->name . '-conf')->first();

                if ($masterData) {
                    $configData = $masterData->metas()->get();
                }
            }

            return view('foundation::config.index', compact('modules', 'selectedModule', 'configData'));

        } catch (\Exception $e) {
            Log::error('Error loading configuration page', [
                'module_id' => $moduleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to load configuration page.');
        }
    }

    /**
     * Update configuration for a specific module
     */
    public function update(Request $request, Module $module)
    {
        try {
            $masterData = MasterData::where('key', $module->name . '-conf')->first();

            if (!$masterData) {
                return redirect()->back()->with('error', 'Configuration not found for this module.');
            }

            $validated = $request->validate([
                'config_keys' => 'required|array',
                'config_values' => 'required|array',
            ]);

            $keys = $validated['config_keys'];
            $values = $validated['config_values'];
            $userId = auth()->id() ?? 1;

            foreach ($keys as $i => $key) {
                $value = $values[$i] ?? '';

                // ensure value is never null
                if (is_null($value)) {
                    $value = '';
                }

                // optionally encode arrays to JSON
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }

                // use model as-is
                $masterData->metas()->updateOrCreate(
                    ['meta_key' => $key],
                    [
                        'meta_value' => $value,
                        'status' => 'active',
                        'updated_by' => $userId,
                        'created_by' => $userId,
                    ]
                );
            }

            Log::info('Module configuration updated successfully', [
                'module_id' => $module->id,
                'module_name' => $module->name,
                'updated_by' => $userId,
            ]);

            return redirect()->back()->with('success', 'Configuration updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating module configuration', [
                'module_id' => $module->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return redirect()->back()->with('error', 'Failed to update configuration.');
        }
    }

}