@extends('userinterface::layouts.app')

@section('content')
<div>
    <a href="{{ route('modules.assign-to-role') }}" class="btn btn-sm btn-outline-secondary mb-3">
        ← Back
    </a>

    <h5 class="fs-6 text-muted mb-3">Module Configuration</h5>

    {{-- Module Selection --}}
    <form id="module-select-form" class="mb-4">
        <div class="row align-items-end">
            <div class="col-md-6">
                <label for="module" class="form-label">Select Module</label>
                <select name="module" id="module" class="form-control" 
                        {{ isset($selectedModule) ? 'disabled' : '' }}>
                    <option value="">Select Module</option>
                    @foreach($modules as $mod)
                        <option value="{{ $mod->id }}"
                            {{ isset($selectedModule) && $selectedModule->id == $mod->id ? 'selected' : '' }}>
                            {{ $mod->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="show-module-btn"
                        {{ isset($selectedModule) ? 'disabled' : '' }}>
                    Show
                </button>
            </div>
        </div>
    </form>

    {{-- Configuration Table --}}
    @if(isset($selectedModule))
    <form method="POST" action="{{ route('modules.config.update', $selectedModule->id) }}">
        @csrf
        @method('PUT')

        <div class="border-0 shadow-sm">
            <div>
                <h5 class="fs-6 text-muted mb-2">Configuration for <strong>{{ $selectedModule->name }}</strong></h5>
            </div>
            <div>
                @if($configData->isEmpty())
                    <div class="alert alert-warning mb-0">No configuration found for this module.</div>
                @else
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($configData as $index => $conf)
                                <tr>
                                    <td>
                                        {{-- Show key as plain text, but still include hidden input for form submission --}}
                                        <span>{{ $conf->meta_key }}</span>
                                        <input type="hidden" name="config_keys[]" value="{{ $conf->meta_key }}">
                                    </td>
                                    <td>
                                        <input type="text" 
                                            name="config_values[]" 
                                            class="form-control form-control-sm" 
                                            value="{{ $conf->meta_value }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="text-end">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            Save Changes
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </form>
    @endif
</div>
@endsection


{{-- JS to redirect dropdown selection to /modules/config/{id} --}}
@push('scripts')
<script>
document.getElementById('show-module-btn').addEventListener('click', function() {
    var select = document.getElementById('module');
    var moduleId = select.value;
    if (moduleId) {
        window.location.href = '/modules/config/' + moduleId;
    } else {
        alert('Please select a module.');
    }
});
</script>
@endpush
