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
        ['label' => 'Primary Fields', 'btn' => 'primary', 'header' => 'primarySection', 'collapse' => 'mainTable', 'tooltip' => 'View main table field structure'],
        ['label' => 'Secondary Fields', 'btn' => 'success', 'header' => 'secondarySection', 'collapse' => 'metaStructure', 'tooltip' => 'View meta table key structure'],
        ['label' => 'APIs', 'btn' => 'warning', 'header' => 'apiSectionHeader', 'collapse' => 'apiSection', 'tooltip' => 'View available API endpoints'],
        ['label' => 'UI', 'btn' => 'dark', 'header' => 'uiSectionHeader', 'collapse' => 'uiSection', 'tooltip' => 'View UI availability and capabilities'],
    ];

    $systemFields = ['id','uid','status','created_by','created_at','updated_by','updated_at','deleted_by','deleted_at'];

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
            <x-foundation::inc-with-props.entity.header :entity="$entity" :showActions="true" />
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
        <x-foundation::inc-with-props.entity.nav-buttons :buttons="$navButtons" />
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
                :editable="false" />
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
                :editable="false" />
        </x-foundation::inc-with-props.entity.accordion-section>

        {{-- API --}}
        <x-foundation::inc-with-props.entity.accordion-section 
            sectionId="apiSectionHeader" 
            title="Available API Endpoints"
            collapseId="apiSection"
            colorClass="warning"
            headerContent="API Details"
            bodyClass="p-0">
            <x-foundation::inc-with-props.entity.api-section :apis="$apis" />
        </x-foundation::inc-with-props.entity.accordion-section>

        {{-- UI --}}
        <x-foundation::inc-with-props.entity.accordion-section 
            sectionId="uiSectionHeader" 
            title="User Interface Availability"
            collapseId="uiSection"
            colorClass="dark"
            headerContent="UI Capabilities"
            bodyClass="">
            <x-foundation::inc-with-props.entity.ui-section :entity="$entity" />
        </x-foundation::inc-with-props.entity.accordion-section>
    </div>
</div>

{{-- Include JS utilities --}}
@push('scripts')
<script src="{{ asset('js/entity-utils.js') }}"></script>
@endpush

@endsection