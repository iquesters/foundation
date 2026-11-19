@extends('userinterface::layouts.app')

@php
    $title = $isCreating ? 'New business entity' : 'Edit '.$businessEntity->name;
    $definitionValue = old('definition', json_encode($definition));
    $tableSchemaUid = $businessEntity?->getMeta('table_schema_uid');

    $tabs = [
        ['route' => 'business-entities.index', 'params' => [], 'icon' => 'fas fa-fw fa-diagram-project', 'label' => 'Business Entities'],
    ];
@endphp

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make([$title]))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('Build a business entity from registered entities'))

@push('styles')
    <link rel="stylesheet" href="{{ \Iquesters\Foundation\Support\Assets::url('css/business-entity-builder.css') }}">
@endpush

@section('content')
    <form id="businessEntityForm" method="POST" novalidate
        action="{{ $isCreating ? route('business-entities.store') : route('business-entities.update', $businessEntity->uid) }}">
        @csrf
        @unless ($isCreating)
            @method('PUT')
        @endunless

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h5 class="fs-6 mb-1">{{ $title }}</h5>
                <p class="text-muted small mb-0">
                    One record saved across several entities. The first part is the root; every other part links to an earlier one.
                </p>
            </div>
            @if ($tableSchemaUid && Route::has('ui.list'))
                <a href="{{ route('ui.list', $tableSchemaUid) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-fw fa-table"></i><span class="ms-1">Records</span>
                </a>
            @endif
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6 col-xl-4">
                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $businessEntity->name ?? '') }}" maxlength="255" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                @unless ($isCreating)
                    <div class="form-text">API slug: <code>{{ $businessEntity->slug }}</code> (does not change)</div>
                @endunless
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <label for="ref_module" class="form-label">Module</label>
                <select id="ref_module" name="ref_module" class="form-select @error('ref_module') is-invalid @enderror">
                    <option value="">None</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module->id }}" @selected(old('ref_module', $businessEntity->ref_module ?? null) == $module->id)>
                            {{ \Illuminate\Support\Str::headline($module->name) }}
                        </option>
                    @endforeach
                </select>
                @error('ref_module')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-xl-4">
                <label for="desc" class="form-label">Description</label>
                <input type="text" id="desc" name="desc" class="form-control @error('desc') is-invalid @enderror"
                    value="{{ old('desc', $businessEntity->desc ?? '') }}" maxlength="255">
                @error('desc')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @error('definition')
            <div class="alert alert-danger small" role="alert">{{ $message }}</div>
        @enderror

        <input type="hidden" id="definition" name="definition" value="{{ $definitionValue }}">

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div id="businessEntityBuilder" class="be-builder" data-definition-input="#definition"></div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-be-add-part>
                    <i class="fa-regular fa-fw fa-plus"></i><span class="ms-1">Add part</span>
                </button>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card be-preview">
                    <div class="card-body">
                        <h6 class="card-title fs-6">Form fields</h6>
                        <p class="small text-muted">Keys used by the generated form and the API.</p>
                        <ul class="list-unstyled small mb-0" data-be-preview></ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('business-entities.index') }}" class="btn btn-sm btn-outline-dark">Cancel</a>
            <button type="submit" class="btn btn-sm btn-primary">{{ $isCreating ? 'Create' : 'Save' }}</button>
        </div>
    </form>

    <script type="application/json" id="businessEntityEntities">@json($entities)</script>
@endsection

@push('scripts')
    <script src="{{ \Iquesters\Foundation\Support\Assets::url('js/business-entity-builder.js') }}"></script>
@endpush
