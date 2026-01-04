@extends('userinterface::layouts.app')

@section('content')
@php
    $isCreating = $isCreating ?? true;
    
    $fields = [];
    $metaFields = [];
    
    if ($entity) {
        $fields = is_string($entity->fields)
            ? json_decode($entity->fields, true)
            : ($entity->fields ?? []);

        $metaFields = is_string($entity->meta_fields)
            ? json_decode($entity->meta_fields, true)
            : ($entity->meta_fields ?? []);
    }

    $navButtons = [
        [
            'label' => 'Primary Fields',
            'btn' => 'primary',
            'header' => 'primarySection',
            'collapse' => 'mainTable',
            'tooltip' => 'Add/Edit main table field structure',
        ],
        [
            'label' => 'Secondary Fields',
            'btn' => 'success',
            'header' => 'secondarySection',
            'collapse' => 'metaStructure',
            'tooltip' => 'Add/Edit meta table key structure',
        ],
        [
            'label' => 'APIs',
            'btn' => 'warning',
            'header' => 'apiSectionHeader',
            'collapse' => 'apiSection',
            'tooltip' => 'View available API endpoints',
        ],
        [
            'label' => 'UI',
            'btn' => 'dark',
            'header' => 'uiSectionHeader',
            'collapse' => 'uiSection',
            'tooltip' => 'View UI availability and capabilities',
        ],
    ];

    $systemFields = [
        'id','uid','status',
        'created_by','created_at',
        'updated_by','updated_at',
        'deleted_by','deleted_at'
    ];

    $apis = $isCreating ? [] : [
        ['GET', 'success', url("/api/entity/index/{$entity->entity_name}")],
        ['GET', 'success', url("/api/entity/list/{$entity->entity_name}")],
        ['POST', 'primary', url("/api/entity/store/{$entity->entity_name}")],
        ['GET', 'success', url("/api/entity/show/{$entity->entity_name}") . '/{uid}'],
        ['PUT', 'warning', url("/api/entity/update/{$entity->entity_name}") . '/{uid}'],
        ['DELETE', 'danger', url("/api/entity/delete/{$entity->entity_name}") . '/{uid}'],
        ['DELETE', 'dark', url("/api/entity/destroy/{$entity->entity_name}") . '/{uid}'],
    ];
@endphp

<form id="entityForm" method="POST" action="{{ $isCreating ? route('entities.store') : route('entities.update', $entity->uid) }}">
    @csrf
    @if(!$isCreating)
        @method('PUT')
    @endif

    <div class="mb-3">
        {{-- HEADER --}}
        <div class="mb-3">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center justify-content-start gap-2">
                    <h5 class="mb-1 fs-6">
                        {{ $isCreating ? 'Create New Entity' : 'Edit Entity: ' . $entity->entity_name }}
                    </h5>
                    @if(!$isCreating)
                        <span class="badge badge-{{ $entity->status }}">{{ ucfirst($entity->status) }}</span>
                    @endif
                </div>
            </div>
            @if(!$isCreating)
                <code>{{ $entity->uid }}</code>
            @else
                <code class="text-muted">UID will be auto-generated</code>
            @endif
        </div>

        <div class="sticky-top entity-sticky-top bg-body pb-2">
            {{-- BASIC INFO --}}
            <div class="mb-3">

                <div class="row px-2">
                    <!-- Entity Name -->
                    <div class="col-md-4">
                        <label for="entity_name" class="form-label">
                            Entity Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                            class="form-control @error('entity_name') is-invalid @enderror" 
                            id="entity_name" 
                            name="entity_name" 
                            value="{{ old('entity_name', $entity->entity_name ?? '') }}"
                            required>
                        @error('entity_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label for="slug" class="form-label">
                            Slug
                        </label>
                        <input type="text" 
                            class="form-control @error('slug') is-invalid @enderror" 
                            id="slug" 
                            name="slug" 
                            value="{{ old('slug', $entity->slug ?? '') }}">
                        @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div class="col-md-4">
                        <label for="desc" class="form-label">Description</label>
                        <textarea class="form-control @error('desc') is-invalid @enderror" 
                                id="desc" 
                                name="desc" 
                                rows="3">{{ old('desc', $entity->desc ?? '') }}</textarea>
                        @error('desc')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- SYSTEM FIELDS INFO --}}
            <div class="alert alert-info mb-3">
                <code>id</code>, <code>uid</code>, <code>status</code>, <code>created_by</code>, <code>created_at</code>, 
                <code>updated_by</code>, <code>updated_at</code>, <code>deleted_by</code>, <code>deleted_at</code>
                those following fields will be automatically managed by the system.
            </div>

            {{-- NAV BUTTONS --}}
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach($navButtons as $btn)
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-{{ $btn['btn'] }}"
                        onclick="openSection('{{ $btn['header'] }}','{{ $btn['collapse'] }}')"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="{{ $btn['tooltip'] }}"
                    >
                        {{ $btn['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="accordion" id="entityAccordion">

            {{-- PRIMARY FIELDS --}}
            <div id="primarySection" class="p-2 text-primary">
                Primary Fields (Main Table Structure)
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button bg-primary-subtle text-primary"
                            data-bs-toggle="collapse"
                            data-bs-target="#mainTable">
                        Field Definitions
                    </button>
                </h2>
                <div id="mainTable" class="accordion-collapse collapse show">
                    <div class="accordion-body p-0">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="fieldsTable">
                                <thead class="table-light">
                                <tr>
                                    <th>#</th><th>Field Name</th><th>Type</th><th>Label</th>
                                    <th>Required</th><th>Nullable</th><th>Input Type</th><th>Default</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody id="fieldsMainTableBody">
                                @forelse($fields as $index => $field)
                                    @if(!in_array($field['name'], $systemFields))
                                        <tr data-index="{{ $index }}">
                                            <td>{{ $loop->iteration }}</td>
                                            <td><input type="text" class="form-control form-control-sm" name="fields[{{ $index }}][name]" value="{{ $field['name'] }}" required></td>
                                            <td>
                                                <select class="form-select form-select-sm" name="fields[{{ $index }}][type]" required>
                                                    <option value="string" {{ $field['type'] == 'string' ? 'selected' : '' }}>String</option>
                                                    <option value="text" {{ $field['type'] == 'text' ? 'selected' : '' }}>Text</option>
                                                    <option value="integer" {{ $field['type'] == 'integer' ? 'selected' : '' }}>Integer</option>
                                                    <option value="decimal" {{ $field['type'] == 'decimal' ? 'selected' : '' }}>Decimal</option>
                                                    <option value="boolean" {{ $field['type'] == 'boolean' ? 'selected' : '' }}>Boolean</option>
                                                    <option value="date" {{ $field['type'] == 'date' ? 'selected' : '' }}>Date</option>
                                                    <option value="datetime" {{ $field['type'] == 'datetime' ? 'selected' : '' }}>DateTime</option>
                                                </select>
                                            </td>
                                            <td><input type="text" class="form-control form-control-sm" name="fields[{{ $index }}][label]" value="{{ $field['label'] }}" required></td>
                                            <td>
                                                <select class="form-select form-select-sm" name="fields[{{ $index }}][required]">
                                                    <option value="1" {{ $field['required'] ? 'selected' : '' }}>YES</option>
                                                    <option value="0" {{ !$field['required'] ? 'selected' : '' }}>NO</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm" name="fields[{{ $index }}][nullable]">
                                                    <option value="1" {{ $field['nullable'] ? 'selected' : '' }}>YES</option>
                                                    <option value="0" {{ !$field['nullable'] ? 'selected' : '' }}>NO</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm" name="fields[{{ $index }}][input_type]" required>
                                                    <option value="text" {{ $field['input_type'] == 'text' ? 'selected' : '' }}>Text</option>
                                                    <option value="textarea" {{ $field['input_type'] == 'textarea' ? 'selected' : '' }}>Textarea</option>
                                                    <option value="number" {{ $field['input_type'] == 'number' ? 'selected' : '' }}>Number</option>
                                                    <option value="email" {{ $field['input_type'] == 'email' ? 'selected' : '' }}>Email</option>
                                                    <option value="date" {{ $field['input_type'] == 'date' ? 'selected' : '' }}>Date</option>
                                                    <option value="checkbox" {{ $field['input_type'] == 'checkbox' ? 'selected' : '' }}>Checkbox</option>
                                                    <option value="select" {{ $field['input_type'] == 'select' ? 'selected' : '' }}>Select</option>
                                                </select>
                                            </td>
                                            <td><input type="text" class="form-control form-control-sm" name="fields[{{ $index }}][default]" value="{{ $field['default'] ?? '' }}"></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr class="no-data-row">
                                        <td colspan="9" class="text-center text-muted">No fields defined. Click "Add Field" to create one.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="p-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPrimaryField()">
                                <i class="fas fa-plus"></i> Field
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECONDARY FIELDS --}}
            <div id="secondarySection" class="p-2 text-success">
                Secondary Fields (Meta Attributes)
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button bg-success-subtle text-sucess"
                            data-bs-toggle="collapse"
                            data-bs-target="#metaStructure">
                        Meta Field Definitions
                    </button>
                </h2>
                <div id="metaStructure" class="accordion-collapse collapse show">
                    <div class="accordion-body p-0">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="metaFieldsTable">
                                <thead class="table-light">
                                <tr>
                                    <th>#</th><th>Meta Key</th><th>Type</th><th>Label</th>
                                    <th>Required</th><th>Nullable</th><th>Input Type</th><th>Display</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody id="metaFieldsTableBody">
                                @forelse($metaFields as $index => $field)
                                    <tr data-index="{{ $index }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td><input type="text" class="form-control form-control-sm" name="meta_fields[{{ $index }}][meta_key]" value="{{ $field['meta_key'] ?? '' }}" required></td>
                                        <td>
                                            <select class="form-select form-select-sm" name="meta_fields[{{ $index }}][type]" required>
                                                <option value="string" {{ ($field['type'] ?? '') == 'string' ? 'selected' : '' }}>String</option>
                                                <option value="text" {{ ($field['type'] ?? '') == 'text' ? 'selected' : '' }}>Text</option>
                                                <option value="integer" {{ ($field['type'] ?? '') == 'integer' ? 'selected' : '' }}>Integer</option>
                                                <option value="decimal" {{ ($field['type'] ?? '') == 'decimal' ? 'selected' : '' }}>Decimal</option>
                                                <option value="boolean" {{ ($field['type'] ?? '') == 'boolean' ? 'selected' : '' }}>Boolean</option>
                                                <option value="json" {{ ($field['type'] ?? '') == 'json' ? 'selected' : '' }}>JSON</option>
                                            </select>
                                        </td>
                                        <td><input type="text" class="form-control form-control-sm" name="meta_fields[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}" required></td>
                                        <td>
                                            <select class="form-select form-select-sm" name="meta_fields[{{ $index }}][required]">
                                                <option value="1" {{ !empty($field['required']) ? 'selected' : '' }}>YES</option>
                                                <option value="0" {{ empty($field['required']) ? 'selected' : '' }}>NO</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" name="meta_fields[{{ $index }}][nullable]">
                                                <option value="1" {{ !empty($field['nullable']) ? 'selected' : '' }}>YES</option>
                                                <option value="0" {{ empty($field['nullable']) ? 'selected' : '' }}>NO</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" name="meta_fields[{{ $index }}][input_type]" required>
                                                <option value="text" {{ ($field['input_type'] ?? 'text') == 'text' ? 'selected' : '' }}>Text</option>
                                                <option value="textarea" {{ ($field['input_type'] ?? '') == 'textarea' ? 'selected' : '' }}>Textarea</option>
                                                <option value="number" {{ ($field['input_type'] ?? '') == 'number' ? 'selected' : '' }}>Number</option>
                                                <option value="select" {{ ($field['input_type'] ?? '') == 'select' ? 'selected' : '' }}>Select</option>
                                                <option value="checkbox" {{ ($field['input_type'] ?? '') == 'checkbox' ? 'selected' : '' }}>Checkbox</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" name="meta_fields[{{ $index }}][display]">
                                                <option value="1" {{ ($field['display'] ?? true) ? 'selected' : '' }}>YES</option>
                                                <option value="0" {{ !($field['display'] ?? true) ? 'selected' : '' }}>NO</option>
                                            </select>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="no-data-row">
                                        <td colspan="9" class="text-center text-muted">No meta fields defined. Click "Add Meta Field" to create one.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="p-2">
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="addMetaField()">
                                <i class="fas fa-plus"></i> Meta Field
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- API --}}
            <div id="apiSectionHeader" class="p-2 text-warning">
                Available API Endpoints
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button bg-warning-subtle text-warning"
                            data-bs-toggle="collapse"
                            data-bs-target="#apiSection">
                        API Details
                    </button>
                </h2>
                <div id="apiSection" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        @if($isCreating)
                            <div class="text-muted">
                                API endpoints will be available after the entity is created.
                            </div>
                        @else
                            <table class="table table-sm table-hover mb-0">
                                <thead><tr><th>Method</th><th>Endpoint</th></tr></thead>
                                <tbody>
                                @foreach($apis as [$method, $color, $endpoint])
                                    <tr>
                                        <td><span class="badge bg-{{ $color }}">{{ $method }}</span></td>
                                        <td><code>{{ $endpoint }}</code></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>

            {{-- UI --}}
            <div id="uiSectionHeader" class="p-2 text-dark">
                User Interface Availability
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button bg-dark-subtle text-dark"
                            data-bs-toggle="collapse"
                            data-bs-target="#uiSection">
                        UI Capabilities
                    </button>
                </h2>
                <div id="uiSection" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        @if($isCreating)
                            <div class="text-muted">
                                UI capabilities will be available after the entity is created.
                            </div>
                        @else
                            <div>✔ List View</div>
                            <div>✔ Detail View</div>
                        @endif
                    </div>
                </div>
            </div>

        </div>

    </div>
    <div class="d-flex align-items-center justify-content-end mb-3 gap-2">
        <a href="{{ route('entities.index') }}" class="btn btn-sm btn-outline-dark">
            Cancel
        </a>
        <button type="submit" class="btn btn-sm btn-outline-primary">
            {{ $isCreating ? 'Create' : 'Update' }}
        </button>
    </div>
</form>

{{-- JS --}}
<script>
let fieldIndex = {{ count($fields) }};
let metaFieldIndex = {{ count($metaFields) }};

function openSection(headerId, collapseId) {
    const header = document.getElementById(headerId);
    const collapse = document.getElementById(collapseId);
    
    // Get the collapse instance
    const collapseInstance = bootstrap.Collapse.getOrCreateInstance(collapse, {
        toggle: false // Don't auto-toggle
    });
    
    // Check if it's currently hidden
    if (!collapse.classList.contains('show')) {
        // If closed, open it
        collapseInstance.show();
    }
    
    // Scroll to the section after a brief delay to allow animation
    setTimeout(() => {
        header.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 150);
}

function toggleSystemFields(checkbox) {
    document.querySelectorAll('.system-field').forEach(row => {
        row.style.display = checkbox.checked ? '' : 'none';
    });
}

function removeRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    updateRowNumbers();
}

function updateRowNumbers() {
    document.querySelectorAll('#fieldsMainTableBody tr:not(.no-data-row)').forEach((row, index) => {
        row.querySelector('td:first-child').textContent = index + 1;
    });
    
    document.querySelectorAll('#metaFieldsTableBody tr:not(.no-data-row)').forEach((row, index) => {
        row.querySelector('td:first-child').textContent = index + 1;
    });
}

function addPrimaryField() {
    const tbody = document.getElementById('fieldsMainTableBody');
    const noDataRow = tbody.querySelector('.no-data-row');
    if (noDataRow) noDataRow.remove();
    
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>${fieldIndex + 1}</td>
        <td><input type="text" class="form-control form-control-sm" name="fields[${fieldIndex}][name]" required></td>
        <td>
            <select class="form-select form-select-sm" name="fields[${fieldIndex}][type]" required>
                <option value="string">String</option>
                <option value="text">Text</option>
                <option value="integer">Integer</option>
                <option value="decimal">Decimal</option>
                <option value="boolean">Boolean</option>
                <option value="date">Date</option>
                <option value="datetime">DateTime</option>
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" name="fields[${fieldIndex}][label]" required></td>
        <td>
            <select class="form-select form-select-sm" name="fields[${fieldIndex}][required]">
                <option value="0" selected>NO</option>
                <option value="1">YES</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="fields[${fieldIndex}][nullable]">
                <option value="1" selected>YES</option>
                <option value="0">NO</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="fields[${fieldIndex}][input_type]" required>
                <option value="text" selected>Text</option>
                <option value="textarea">Textarea</option>
                <option value="number">Number</option>
                <option value="email">Email</option>
                <option value="date">Date</option>
                <option value="checkbox">Checkbox</option>
                <option value="select">Select</option>
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" name="fields[${fieldIndex}][default]"></td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);
    fieldIndex++;
}

function addMetaField() {
    const tbody = document.getElementById('metaFieldsTableBody');
    const noDataRow = tbody.querySelector('.no-data-row');
    if (noDataRow) noDataRow.remove();
    
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>${metaFieldIndex + 1}</td>
        <td><input type="text" class="form-control form-control-sm" name="meta_fields[${metaFieldIndex}][meta_key]" required></td>
        <td>
            <select class="form-select form-select-sm" name="meta_fields[${metaFieldIndex}][type]" required>
                <option value="string" selected>String</option>
                <option value="text">Text</option>
                <option value="integer">Integer</option>
                <option value="decimal">Decimal</option>
                <option value="boolean">Boolean</option>
                <option value="json">JSON</option>
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" name="meta_fields[${metaFieldIndex}][label]" required></td>
        <td>
            <select class="form-select form-select-sm" name="meta_fields[${metaFieldIndex}][required]">
                <option value="0" selected>NO</option>
                <option value="1">YES</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="meta_fields[${metaFieldIndex}][nullable]">
                <option value="1" selected>YES</option>
                <option value="0">NO</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="meta_fields[${metaFieldIndex}][input_type]" required>
                <option value="text" selected>Text</option>
                <option value="textarea">Textarea</option>
                <option value="number">Number</option>
                <option value="select">Select</option>
                <option value="checkbox">Checkbox</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="meta_fields[${metaFieldIndex}][display]">
                <option value="1" selected>YES</option>
                <option value="0">NO</option>
            </select>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);
    metaFieldIndex++;
}

// Form submission with JSON conversion
document.getElementById('entityForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const fields = [];
    const metaFields = [];
    
    // Collect all field data
    document.querySelectorAll('#fieldsMainTableBody tr:not(.no-data-row)').forEach(row => {
        const inputs = row.querySelectorAll('input, select');
        const field = {
            name: inputs[0].value,
            type: inputs[1].value,
            label: inputs[2].value,
            required: inputs[3].value === '1',
            nullable: inputs[4].value === '1',
            input_type: inputs[5].value,
            default: inputs[6].value || null
        };
        fields.push(field);
    });
    
    // Collect all meta field data
    document.querySelectorAll('#metaFieldsTableBody tr:not(.no-data-row)').forEach(row => {
        const inputs = row.querySelectorAll('input, select');
        const metaField = {
            meta_key: inputs[0].value,
            type: inputs[1].value,
            label: inputs[2].value,
            required: inputs[3].value === '1',
            nullable: inputs[4].value === '1',
            input_type: inputs[5].value,
            display: inputs[6].value === '1'
        };
        metaFields.push(metaField);
    });
    
    // Remove all field inputs from form
    document.querySelectorAll('input[name^="fields["], select[name^="fields["]').forEach(el => el.remove());
    document.querySelectorAll('input[name^="meta_fields["], select[name^="meta_fields["]').forEach(el => el.remove());
    
    // Add JSON data
    const fieldsInput = document.createElement('input');
    fieldsInput.type = 'hidden';
    fieldsInput.name = 'fields';
    fieldsInput.value = JSON.stringify(fields);
    this.appendChild(fieldsInput);
    
    const metaFieldsInput = document.createElement('input');
    metaFieldsInput.type = 'hidden';
    metaFieldsInput.name = 'meta_fields';
    metaFieldsInput.value = JSON.stringify(metaFields);
    this.appendChild(metaFieldsInput);
    
    this.submit();
});
</script>

@endsection