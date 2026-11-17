@extends('userinterface::layouts.app')

@section('content')
<div>
    <a href="{{ route('modules.assign-to-role') }}" class="btn btn-sm btn-outline-secondary mb-3">← Back</a>

    <h5 class="fs-6 text-muted mb-3">Module Settings</h5>

    {{-- Module selection --}}
    <form id="module-select-form" class="mb-4">
        <div class="row align-items-end g-2">
            <div class="col-md-6">
                <label for="module" class="form-label">Module</label>
                <select name="module" id="module" class="form-select">
                    <option value="">Select module</option>
                    @foreach($modules as $mod)
                        <option value="{{ $mod->id }}" @selected(isset($selectedModule) && $selectedModule->id == $mod->id)>{{ $mod->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="show-module-btn">Show</button>
            </div>
        </div>
    </form>

    @if(isset($selectedModule))
        @if(empty($settings))
            <div class="alert alert-warning">This module has no settings.</div>
        @else
            @php
                $field = fn (string $path) => str_replace('.', '__', $path);
                $display = fn ($v) => is_bool($v) ? ($v ? 'on' : 'off') : (is_array($v) ? json_encode($v) : ($v === null || $v === '' ? '—' : (string) $v));
                $groups = collect($settings)->groupBy(fn ($row) => str_contains($row['path'], '.') ? \Illuminate\Support\Str::beforeLast($row['path'], '.') : '');
            @endphp

            <form method="POST" action="{{ route('modules.config.update', $selectedModule->id) }}">
                @csrf
                @method('PUT')

                <p class="small text-muted">
                    Only values you change are saved; everything else follows the module's defaults.
                    Tick <em>Reset</em> to return a setting to its default.
                </p>

                @foreach($groups as $group => $rows)
                    <h6 class="mt-4 mb-2 text-secondary">{{ $group === '' ? 'General' : \Illuminate\Support\Str::headline(str_replace('.', ' › ', $group)) }}</h6>
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 30%">Setting</th>
                                <th>Value</th>
                                <th style="width: 20%">Default</th>
                                <th style="width: 6%" class="text-center">Reset</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                            @php
                                $name = 'settings[' . $field($row['path']) . ']';
                                $errorKey = 'settings.' . $field($row['path']);
                                $changed = $row['value'] !== $row['default'];
                                $old = old('settings.' . $field($row['path']));
                                $options = is_string($row['rule']) && preg_match('/(?:^|\|)in:([^|]+)/', $row['rule'], $m) ? explode(',', $m[1]) : null;
                            @endphp
                            <tr>
                                <td>
                                    <code class="small">{{ \Illuminate\Support\Str::afterLast($row['path'], '.') }}</code>
                                    @if($changed)<span class="badge text-bg-info ms-1">changed</span>@endif
                                </td>
                                <td>
                                    @if($row['type'] === 'bool')
                                        <input type="hidden" name="{{ $name }}" value="0">
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" value="1"
                                                   @checked($old !== null ? (bool) $old : $row['value'])>
                                        </div>
                                    @elseif($row['secret'])
                                        <input type="password" name="{{ $name }}" autocomplete="new-password"
                                               class="form-control form-control-sm @error($errorKey) is-invalid @enderror"
                                               placeholder="{{ ($row['value'] ?? '') !== '' ? '•••••••• (set; leave blank to keep)' : 'Not set' }}">
                                    @elseif($options)
                                        <select name="{{ $name }}" class="form-select form-select-sm @error($errorKey) is-invalid @enderror">
                                            @foreach($options as $option)
                                                <option value="{{ $option }}" @selected(($old ?? $row['value']) == $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @elseif(in_array($row['type'], ['int', 'float'], true))
                                        <input type="number" name="{{ $name }}" step="{{ $row['type'] === 'int' ? 1 : 'any' }}"
                                               value="{{ $old ?? $row['value'] }}"
                                               class="form-control form-control-sm @error($errorKey) is-invalid @enderror">
                                    @elseif($row['type'] === 'array')
                                        <textarea name="{{ $name }}" rows="2"
                                                  class="form-control form-control-sm font-monospace @error($errorKey) is-invalid @enderror">{{ $old ?? json_encode($row['value'], JSON_UNESCAPED_SLASHES) }}</textarea>
                                    @else
                                        <input type="text" name="{{ $name }}" value="{{ $old ?? $row['value'] }}"
                                               class="form-control form-control-sm @error($errorKey) is-invalid @enderror">
                                    @endif
                                    @error($errorKey)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </td>
                                <td class="small text-muted text-break">{{ $row['secret'] ? '—' : $display($row['default']) }}</td>
                                <td class="text-center">
                                    @if($changed)
                                        <input class="form-check-input" type="checkbox" name="reset[]" value="{{ $row['path'] }}" title="Reset to default">
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endforeach

                <div class="text-end">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Save settings</button>
                </div>
            </form>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
document.getElementById('show-module-btn').addEventListener('click', function () {
    var moduleId = document.getElementById('module').value;
    if (moduleId) {
        window.location.href = '/modules/config/' + moduleId;
    }
});
</script>
@endpush
