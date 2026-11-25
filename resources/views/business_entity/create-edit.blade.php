@extends('userinterface::layouts.app')

@php
    $title = $isCreating ? 'New business entity' : 'Edit '.$businessEntity->name;
    $definitionValue = old('definition', json_encode($definition));
    $tableSchemaUid = $businessEntity?->getMeta('table_schema_uid');
    $currentOutput = old('output', $output);
    $builderData = [
        'entities' => $entities,
        'models' => $models,
        'relationTypes' => $relationTypes,
        'urls' => [
            'check' => route('business-entities.check'),
            'columns' => route('business-entities.columns'),
            'createEntity' => Route::has('entities.create') ? route('entities.create') : null,
        ],
    ];
    $screens = [
        'form_table' => ['A form and its own list', 'Gets its own page listing the records, with Create, view and edit.'],
        'form' => ['A form only', 'No list of its own. Choose a table below whose Create and edit should open this form.'],
        'api' => ['API only', 'No screens. For developers: a module\'s own form saves through the business entity API.'],
    ];

@endphp

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make([$title]))
@section('breadcrumb', $title)
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
                    A business entity saves one record across several entities, for example a user with their roles, or a customer with their address and notes.
                </p>
            </div>
            @if ($tableSchemaUid && Route::has('ui.list'))
                <a href="{{ route('ui.list', $tableSchemaUid) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-fw fa-table"></i><span class="ms-1">Records</span>
                </a>
            @endif
        </div>

        <details class="be-guide card card-body small mb-3" @if ($isCreating) open @endif>
            <summary class="fw-medium">How it works</summary>
            <ol class="mb-1 mt-2 ps-3">
                <li><strong>Name it</strong> and choose where it should appear (its own list, a form inside another table, or API only).</li>
                <li><strong>Add entities.</strong> The first one is the <em>primary</em> entity: each record is one of its rows. Add the others it should include, such as an address, notes, or roles.</li>
                <li><strong>Link them.</strong> For each added entity, choose how it relates to an earlier one; the sentence on the card says it in words. Pick the fields the form should show.</li>
                <li><strong>Review &amp; save.</strong> Everything is checked against the database first, and you see the whole structure before saving.</li>
            </ol>
            <p class="text-muted mb-0">
                Nothing here creates or changes database tables: a business entity only describes how existing tables relate.
                If a link needs a table that doesn't exist yet (e.g. a link table for many-to-many), create it as an entity first.
            </p>
        </details>

        @if ($errors->has('definition'))
            <div class="alert alert-danger small" role="alert">
                <div class="fw-medium mb-1">This business entity can't be saved yet. Please fix:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->get('definition') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <h6 class="be-step fs-6 mb-2">1. Details</h6>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-4">
                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $businessEntity->name ?? '') }}" maxlength="255" required placeholder="e.g. Customer Profile">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    @if ($isCreating)
                        Shown in lists and form titles. Its web address (slug) is made from the name and can't change later.
                    @else
                        Web address (slug): <code>{{ $businessEntity->slug }}</code>, which stays the same if you rename it.
                    @endif
                </div>
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
                <div class="form-text">Optional: the module it belongs to.</div>
            </div>
            <div class="col-12 col-xl-4">
                <label for="desc" class="form-label">Description</label>
                <input type="text" id="desc" name="desc" class="form-control @error('desc') is-invalid @enderror"
                    value="{{ old('desc', $businessEntity->desc ?? '') }}" maxlength="255" placeholder="What is it for?">
                @error('desc')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">Optional: a note for other admins.</div>
            </div>
        </div>

        <h6 class="be-step fs-6 mb-2">2. Where it appears</h6>
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-7">
                @foreach ($screens as $value => [$label, $help])
                    @php($checked = $currentOutput === $value || ($value === 'form_table' && ! array_key_exists($currentOutput, $screens)))
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="output" id="output_{{ $value }}" value="{{ $value }}" @checked($checked)>
                        <label class="form-check-label" for="output_{{ $value }}">
                            {{ $label }}
                            <span class="d-block small text-muted">{{ $help }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="col-12 col-lg-5">
                <label for="use_in_table" class="form-label">Use the form in another table</label>
                <select id="use_in_table" name="use_in_table" class="form-select @error('use_in_table') is-invalid @enderror">
                    <option value="">No, only here</option>
                    @foreach ($tableSchemas as $tableSlug => $tableName)
                        <option value="{{ $tableSlug }}" @selected(old('use_in_table', $useInTable) === $tableSlug)>{{ $tableName }} ({{ $tableSlug }})</option>
                    @endforeach
                </select>
                @error('use_in_table')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    That table's <strong>Create</strong> button and its rows will open this form instead of their own; its columns stay the same.
                    Choose "No, only here" (or delete this business entity) to give the table its own form back.
                </div>
            </div>
        </div>

        <input type="hidden" id="definition" name="definition" value="{{ $definitionValue }}">

        <h6 class="be-step fs-6 mb-2">3. Entities and how they link</h6>
        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-be-add-part>
                        <i class="fa-regular fa-fw fa-plus"></i><span class="ms-1">Add entity</span>
                    </button>
                    <span class="small text-muted">The first entity you add is the primary one.</span>
                </div>
                <div id="businessEntityBuilder" class="be-builder" data-definition-input="#definition"></div>
                {{-- Used when the UI modal (modal.js) is not loaded. --}}
                <div id="beInlinePanel" class="card card-body mt-2 d-none"></div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card be-preview">
                    <div class="card-body">
                        <h6 class="card-title fs-6">What the form will have</h6>
                        <p class="small text-muted">Updated as you choose. Developers: these are also the keys the API uses.</p>
                        <ul class="list-unstyled small mb-0" data-be-preview></ul>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="be-step fs-6 mt-4 mb-2">4. Review and save</h6>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted">You'll see the whole structure and any problems before anything is saved.</span>
            <div class="d-flex gap-2">
                <a href="{{ route('business-entities.index') }}" class="btn btn-sm btn-outline-dark">Cancel</a>
                <button type="submit" class="btn btn-sm btn-primary" data-be-review>
                    <i class="fas fa-fw fa-list-check"></i><span class="ms-1">Review &amp; {{ $isCreating ? 'create' : 'save' }}</span>
                </button>
            </div>
        </div>
    </form>

    <script type="application/json" id="businessEntityData">@json($builderData)</script>
    <datalist id="beKnownTables">
        @foreach (collect($entities)->pluck('table')->merge(collect($models)->pluck('table'))->filter()->unique() as $knownTable)
            <option value="{{ $knownTable }}"></option>
        @endforeach
    </datalist>
@endsection

@push('scripts')
    <script src="{{ \Iquesters\Foundation\Support\Assets::url('js/business-entity-builder.js') }}"></script>
@endpush
