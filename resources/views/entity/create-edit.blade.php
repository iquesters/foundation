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
        ['label' => 'Primary Fields', 'btn' => 'primary', 'header' => 'primarySection', 'collapse' => 'mainTable', 'tooltip' => 'Add/Edit main table field structure'],
        ['label' => 'Secondary Fields', 'btn' => 'success', 'header' => 'secondarySection', 'collapse' => 'metaStructure', 'tooltip' => 'Add/Edit meta table key structure'],
        ['label' => 'APIs', 'btn' => 'warning', 'header' => 'apiSectionHeader', 'collapse' => 'apiSection', 'tooltip' => 'View available API endpoints'],
        ['label' => 'UI', 'btn' => 'dark', 'header' => 'uiSectionHeader', 'collapse' => 'uiSection', 'tooltip' => 'View UI availability and capabilities'],
    ];

    $systemFields = ['id','uid','status','created_by','created_at','updated_by','updated_at','deleted_by','deleted_at'];

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
        <div class="sticky-top entity-sticky-top bg-body pb-2">
            {{-- HEADER --}}
            <div class="mb-3">
                <x-foundation::inc-with-props.entity.header :entity="$entity ?? null" :isCreating="$isCreating" />
            </div>

            {{-- BASIC INFO --}}
            <div class="mb-3">
                <div class="row px-2">
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
                        <label for="slug" class="form-label">Slug</label>
                        <input type="text" 
                            class="form-control @error('slug') is-invalid @enderror" 
                            id="slug" 
                            name="slug" 
                            value="{{ old('slug', $entity->slug ?? '') }}">
                        @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

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
            <div class="mb-3">
                <x-foundation::inc-with-props.entity.nav-buttons :buttons="$navButtons" />
            </div>
        </div>

        <div class="accordion" id="entityAccordion">
            {{-- PRIMARY FIELDS --}}
            <x-foundation::inc-with-props.entity.accordion-section 
                sectionId="primarySection" 
                title="Primary Fields (Main Table Structure)"
                collapseId="mainTable"
                colorClass="primary"
                headerContent="Field Definitions">
                <x-foundation::inc-with-props.entity.fields-table 
                    :fields="$fields" 
                    :systemFields="$systemFields" 
                    :editable="true" />
            </x-foundation::inc-with-props.entity.accordion-section>

            {{-- SECONDARY FIELDS --}}
            <x-foundation::inc-with-props.entity.accordion-section 
                sectionId="secondarySection" 
                title="Secondary Fields (Meta Attributes)"
                collapseId="metaStructure"
                colorClass="success"
                headerContent="Meta Field Definitions">
                <x-foundation::inc-with-props.entity.meta-fields-table 
                    :metaFields="$metaFields" 
                    :editable="true" />
            </x-foundation::inc-with-props.entity.accordion-section>

            {{-- API --}}
            <x-foundation::inc-with-props.entity.accordion-section 
                sectionId="apiSectionHeader" 
                title="Available API Endpoints"
                collapseId="apiSection"
                colorClass="warning"
                headerContent="API Details"
                bodyClass="">
                <x-foundation::inc-with-props.entity.api-section :apis="$apis" :isCreating="$isCreating" />
            </x-foundation::inc-with-props.entity.accordion-section>

            {{-- UI --}}
            <x-foundation::inc-with-props.entity.accordion-section 
                sectionId="uiSectionHeader" 
                title="User Interface Availability"
                collapseId="uiSection"
                colorClass="dark"
                headerContent="UI Capabilities"
                bodyClass="">
                <x-foundation::inc-with-props.entity.ui-section :entity="$entity ?? null" :isCreating="$isCreating" />
            </x-foundation::inc-with-props.entity.accordion-section>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-end mb-3 gap-2">
        <a href="{{ route('entities.index') }}" class="btn btn-sm btn-outline-dark">Cancel</a>
        <button type="submit" class="btn btn-sm btn-outline-primary">
            {{ $isCreating ? 'Create' : 'Update' }}
        </button>
    </div>
</form>

{{-- Include JS utilities --}}
@push('scripts')
<script src="{{ asset('js/entity-utils.js') }}"></script>
<script>
// Form submission with JSON conversion
document.getElementById('entityForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fields = [];
    const metaFields = [];
    
    // Collect all field data
    document.querySelectorAll('#fieldsMainTableBody tr:not(.no-data-row)').forEach(row => {
        const inputs = row.querySelectorAll('input, select');
        fields.push({
            name: inputs[0].value,
            type: inputs[1].value,
            label: inputs[2].value,
            required: inputs[3].value === '1',
            nullable: inputs[4].value === '1',
            input_type: inputs[5].value,
            default: inputs[6].value || null
        });
    });
    
    // Collect all meta field data
    document.querySelectorAll('#metaFieldsTableBody tr:not(.no-data-row)').forEach(row => {
        const inputs = row.querySelectorAll('input, select');
        metaFields.push({
            meta_key: inputs[0].value,
            type: inputs[1].value,
            label: inputs[2].value,
            required: inputs[3].value === '1',
            nullable: inputs[4].value === '1',
            input_type: inputs[5].value,
            display: inputs[6].value === '1'
        });
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
@endpush

@endsection