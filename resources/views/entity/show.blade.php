@extends('userinterface::layouts.app')

@section('content')
@php
    $fields = is_string($entity->fields)
        ? json_decode($entity->fields, true)
        : ($entity->fields ?? []);

    $metaFields = is_string($entity->meta_fields)
        ? json_decode($entity->meta_fields, true)
        : ($entity->meta_fields ?? []);

    $navButtons = [
        [
            'label' => 'Primary Fields',
            'btn' => 'primary',
            'header' => 'primarySection',
            'collapse' => 'mainTable',
            'tooltip' => 'View main table field structure',
        ],
        [
            'label' => 'Secondary Fields',
            'btn' => 'success',
            'header' => 'secondarySection',
            'collapse' => 'metaStructure',
            'tooltip' => 'View meta table key structure',
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

    $apis = [
        ['GET', 'success', url("/api/entity/index/{$entity->entity_name}")],
        ['GET', 'success', url("/api/entity/list/{$entity->entity_name}")],
        ['POST', 'primary', url("/api/entity/store/{$entity->entity_name}")],
        ['GET', 'success', url("/api/entity/show/{$entity->entity_name}") . '/{uid}'],
        ['PUT', 'warning', url("/api/entity/update/{$entity->entity_name}") . '/{uid}'],
        ['DELETE', 'danger', url("/api/entity/delete/{$entity->entity_name}") . '/{uid}'],
        ['DELETE', 'dark', url("/api/entity/destroy/{$entity->entity_name}") . '/{uid}'],
    ];
@endphp

<div>
    <div class="sticky-top entity-sticky-top bg-body pb-2">
        {{-- HEADER --}}
        <div class="mb-1">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center justify-content-start gap-2">
                    <h5 class="mb-1 fs-6">
                        {{ $entity->entity_name }}
                    </h5>
                    <span class="badge badge-{{ $entity->status }}">{{ ucfirst($entity->status) }}</span>
                </div>
                <div class="d-flex align-items-center justify-content-end gap-2 pt-1">
                    <a href="{{ route('entities.edit', $entity->uid) }}" class="btn btn-sm btn-outline-dark d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-fw fa-edit"></i> Edit
                    </a>
                    @if ($entity->status !== 'published')
                        <form action="{{ route('entities.publish', $entity->uid) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                            @csrf
                            <button class="btn btn-sm btn-outline-success d-flex align-items-center justify-content-center gap-2">
                                <i class="fas fa-fw fa-upload"></i> Publish
                            </button>
                        </form>
                    @elseif ($entity->status !== 'deleted')
                        <form action="{{ route('entities.destroy', $entity->uid) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                            @csrf
                            @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger d-flex align-items-center justify-content-center gap-2">
                                    <i class="fas fa-fw fa-trash"></i> Delete
                                </button>
                        </form>
                    @endif
                </div>
            </div>
            <code>{{ $entity->uid }}</code>
            <br>
            <small class="mb-0">Slug: {{ $entity->slug }}</small>
            <br>
            <small class="mb-0">Description: {{ $entity->desc }}</small>
        </div>
    
        {{-- SYSTEM FIELD TOGGLE --}}
        <div class="mb-3 form-check">
            <input class="form-check-input"
                type="checkbox"
                id="toggleSystemFields"
                checked
                onchange="toggleSystemFields(this)">
            <label class="form-check-label fw-semibold" for="toggleSystemFields">
                Show system-generated fields (ID, timestamps, audit columns)
            </label>
        </div>

        {{-- NAV BUTTONS --}}
        <div class="d-flex flex-wrap gap-2">
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
                <div class="accordion-body p-0 table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>#</th><th>Field</th><th>Type</th><th>Label</th>
                            <th>Required</th><th>Nullable</th><th>Input</th><th>Default</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($fields as $field)
                            @php $isSystem = in_array($field['name'], $systemFields); @endphp
                            <tr class="{{ $isSystem ? 'system-field' : '' }}">
                                <td>{{ $loop->iteration }}</td>
                                <td><code>{{ $field['name'] }}</code></td>
                                <td>{{ $field['type'] }}</td>
                                <td>{{ $field['label'] }}</td>
                                <td>{{ $field['required'] ? 'YES' : 'NO' }}</td>
                                <td>{{ $field['nullable'] ? 'YES' : 'NO' }}</td>
                                <td>{{ $field['input_type'] }}</td>
                                <td>{{ $field['default'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">No fields defined</td></tr>
                        @endforelse
                        </tbody>
                    </table>
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
                <div class="accordion-body p-0 table-responsive">
                    <table class="table table-bordered table-striped mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>#</th><th>Meta Key</th><th>Type</th><th>Label</th>
                            <th>Required</th><th>Nullable</th><th>Input</th><th>Display</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($metaFields as $key => $field)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><code>{{ $field['meta_key'] ?? $key }}</code></td>
                                <td>{{ $field['type'] ?? '—' }}</td>
                                <td>{{ $field['label'] ?? '—' }}</td>
                                <td>{{ !empty($field['required']) ? 'YES' : 'NO' }}</td>
                                <td>{{ !empty($field['nullable']) ? 'YES' : 'NO' }}</td>
                                <td>{{ $field['input_type'] ?? 'text' }}</td>
                                <td>{{ ($field['display'] ?? true) ? 'YES' : 'NO' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">No meta fields</td></tr>
                        @endforelse
                        </tbody>
                    </table>
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
                <div class="accordion-body p-0">
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
                    <div class="d-flex align-items-center justify-content-start gap-2">
                        <div>
                            @if ($entity->getMeta('table_schema_uid'))
                                <a href="{{ route('ui.list', $entity->getMeta('table_schema_uid')) }}" class="btn btn-sm btn-outline-info d-flex align-items-center justify-content-center gap-2">
                                    <i class="fas fa-fw fa-list"></i> 
                                    List View
                                </a>
                            @else
                                List View Not Available
                            @endif
                        </div>
    
                        <div>
                            @if ($entity->getMeta('form_schema_uid'))
                                <a href="#" class="btn btn-sm btn-outline-dark d-flex align-items-center justify-content-center gap-2">
                                    <i class="fas fa-fw fa-file-alt"></i>
                                    Detail View
                                </a>
                            @else
                                Detail View Not Available
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- JS --}}
<script>
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
</script>

@endsection