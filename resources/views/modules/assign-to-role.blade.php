@extends('userinterface::layouts.app')

@section('content')
    <div>
        <div>
            <h5 class="fs-6 text-muted">Assign Modules to Role</h5>
        </div>
        <div>
            <!-- Role Selection Form -->
            <form action="" method="GET" class="mb-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="role">Select Role</label>
                            <select name="role_id" id="role" class="form-control" onchange="this.form.submit()">
                                <option value="">Select a Role</option>
                                @foreach($roles as $roleItem)
                                    <option value="{{ $roleItem->id }}" 
                                        {{ $selectedRole && $selectedRole->id == $roleItem->id ? 'selected' : '' }}>
                                        {{ $roleItem->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </form>

            @if($selectedRole)
                <!-- Module Assignment Form -->
                <form action="{{ route('modules.update-role-modules', $selectedRole) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <h5 class="fs-6 text-muted">Assign Modules to Role: <strong>{{ $selectedRole->name }}</strong></h5>
                    
                    <div class="row mt-2">
                        @foreach($modules as $module)
                            <div class="col-md-4 mb-3">
                                <div class="form-check">
                                    <input type="checkbox" 
                                            name="modules[]" 
                                            value="{{ $module->id }}" 
                                            id="module_{{ $module->id }}"
                                            class="form-check-input"
                                            {{ $module->isAssignedToRole($selectedRole) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="module_{{ $module->id }}">
                                        <i class="{{ $module->getMeta('module_icon') ?? 'fas fa-cube' }} me-2"></i>
                                        {{ $module->name }}
                                    </label>
                                    @if($module->description)
                                        <small class="form-text text-muted d-block">
                                            {{ $module->description }}
                                        </small>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($modules->count() > 0)
                    <div class="d-flex align-items-center justify-content-start gap-2">
                        <a href="{{ route('modules.index') }}" class="btn btn-sm btn-outline-dark">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            Save Assignments
                        </button>
                    </div>
                    @else
                    <div class="alert alert-warning">
                        No active modules found.
                    </div>
                    @endif
                </form>
            @else
                <div class="alert alert-info">
                    Please select a role to assign modules.
                </div>
            @endif
        </div>
    </div>
@endsection