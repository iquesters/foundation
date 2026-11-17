<?php

namespace Iquesters\Foundation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Iquesters\Foundation\Models\Module;
use Iquesters\Foundation\Support\BaseConf;
use Iquesters\Foundation\Support\ConfProvider;
use Iquesters\Foundation\System\Traits\Loggable;

/**
 * Admin screen for module settings (typed, validated; see BaseConf).
 * Only changed values are stored; "Reset" returns a setting to its code default.
 */
class ConfigController extends Controller
{
    use Loggable;

    public function index(Request $request, $moduleId = null)
    {
        $modules = Module::active()->get();
        $selectedModule = $moduleId ? Module::find($moduleId) : null;
        $settings = [];

        if ($selectedModule && ($conf = $this->confFor($selectedModule))) {
            $settings = $conf->describe();
        }

        return view('foundation::config.index', compact('modules', 'selectedModule', 'settings'));
    }

    public function update(Request $request, Module $module)
    {
        $conf = $this->confFor($module);

        if (!$conf) {
            return redirect()->back()->with('error', 'This module has no settings.');
        }

        $changes = [];
        $errors = [];
        $submitted = (array) $request->input('settings', []);
        $reset = (array) $request->input('reset', []);

        foreach ($conf->describe() as $row) {
            $path = $row['path'];
            $field = $this->fieldName($path);

            if (in_array($path, $reset, true)) {
                data_set($changes, $path, $row['default']);
                continue;
            }

            if (!array_key_exists($field, $submitted)) {
                continue;
            }

            $value = $submitted[$field];

            // Secrets are never sent to the browser: blank means "keep the current value".
            if ($row['secret'] && ($value === null || $value === '')) {
                continue;
            }

            if ($row['type'] === 'array') {
                $decoded = json_decode((string) $value, true);
                if (!is_array($decoded)) {
                    $errors["settings.{$field}"] = 'Must be valid JSON (a list or object).';
                    continue;
                }
                $value = $decoded;
            } elseif ($row['nullable'] && $value === '' && $row['type'] !== 'string') {
                $value = null;
            }

            data_set($changes, $path, $value);
        }

        if ($errors) {
            return redirect()->back()->withErrors($errors)->withInput();
        }

        try {
            $conf->update($changes, auth()->id());
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $path => $list) {
                $messages['settings.' . $this->fieldName($path)] = $list[0];
            }

            return redirect()->back()->withErrors($messages)->withInput();
        }

        static::logInfo('Module settings updated', ['module' => $module->name, 'user_id' => auth()->id()]);

        return redirect()->back()->with('success', 'Settings saved.');
    }

    /** Registered settings of a module (Module::name is the package name, e.g. "user-management"). */
    private function confFor(Module $module): ?BaseConf
    {
        foreach (array_keys(ConfProvider::registered()) as $identifier) {
            if (Str::afterLast($identifier, '/') === $module->name) {
                return ConfProvider::from($identifier);
            }
        }

        return null;
    }

    /** Dot paths can't be form field names directly ("a.b" would nest); use "a__b". */
    private function fieldName(string $path): string
    {
        return str_replace('.', '__', $path);
    }
}
