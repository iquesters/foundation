@extends('userinterface::layouts.app')

@php
    $isNew = ! $item->exists;
    $title = $isNew ? ($item->ref_parent ? 'New tab' : 'New sidebar item') : 'Edit "'.$item->label.'"';
    $currentParent = old('parent', $item->ref_parent ? $parents->firstWhere('id', $item->ref_parent)?->uid : null);
    $currentTarget = old('target', $target);
@endphp

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make([$title]))
@section('breadcrumb', $title)

@section('content')
    <form method="POST" action="{{ $isNew ? route('navigation-items.store') : route('navigation-items.update', $item->uid) }}" class="row g-3" style="max-width: 760px;">
        @csrf
        @unless ($isNew) @method('PUT') @endunless
        <input type="hidden" name="module" value="{{ $module->uid }}">

        <div class="col-12">
            <h5 class="fs-6 mb-1">{{ $title }}</h5>
            <p class="small text-muted mb-0">
                Module: {{ \Illuminate\Support\Str::headline($module->name) }}.
                @if ($item->source === 'module' && ! $isNew)
                    This item comes from the module: your changes are kept when the module is updated.
                @endif
            </p>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="label">Label <span class="text-danger">*</span></label>
            <input class="form-control @error('label') is-invalid @enderror" id="label" name="label" value="{{ old('label', $item->label) }}" required maxlength="100">
            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="icon">Icon</label>
            <input class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" value="{{ old('icon', $item->icon) }}" placeholder="fas fa-users">
            <div class="form-text">Font Awesome classes.</div>
            @error('icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="parent">Where</label>
            <select class="form-select @error('parent') is-invalid @enderror" id="parent" name="parent">
                <option value="">In the sidebar</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->uid }}" @selected($currentParent === $parent->uid)>As a tab of "{{ $parent->label }}"</option>
                @endforeach
            </select>
            @error('parent')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="sort">Order</label>
            <input type="number" class="form-control" id="sort" name="sort" min="0" value="{{ old('sort', $item->sort) }}">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="status">Shown</label>
            <select class="form-select" id="status" name="status">
                <option value="active" @selected(old('status', $item->status) === 'active')>Yes</option>
                <option value="inactive" @selected(old('status', $item->status) === 'inactive')>No, hidden</option>
            </select>
        </div>

        <div class="col-12">
            <div class="form-label">Opens</div>
            @foreach (['table' => 'A table (list page)', 'route' => 'A page of a module (route)', 'url' => 'A link'] as $value => $text)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="target" id="target_{{ $value }}" value="{{ $value }}" @checked($currentTarget === $value)
                           onchange="document.querySelectorAll('[data-target-field]').forEach(el => el.hidden = el.dataset.targetField !== this.value)">
                    <label class="form-check-label" for="target_{{ $value }}">{{ $text }}</label>
                </div>
            @endforeach
        </div>

        <div class="col-12" data-target-field="table" @if ($currentTarget !== 'table') hidden @endif>
            <label class="form-label" for="table_schema_uid">Table</label>
            <select class="form-select @error('table_schema_uid') is-invalid @enderror" id="table_schema_uid" name="table_schema_uid">
                <option value="">Choose…</option>
                @foreach ($tableSchemas as $uid => $name)
                    <option value="{{ $uid }}" @selected(old('table_schema_uid', $item->table_schema_uid) === $uid)>{{ $name }}</option>
                @endforeach
            </select>
            @error('table_schema_uid')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12" data-target-field="route" @if ($currentTarget !== 'route') hidden @endif>
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="route">Route name</label>
                    <input class="form-control @error('route') is-invalid @enderror" id="route" name="route" value="{{ old('route', $item->route) }}" placeholder="e.g. jobs.failed">
                    @error('route')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="params">Route parameters (JSON)</label>
                    <input class="form-control @error('params') is-invalid @enderror" id="params" name="params" value="{{ old('params', $item->params ? json_encode($item->params) : '') }}" placeholder='{"organisationUid": null}'>
                    <div class="form-text">null takes the value of the current page (for tabs of a record).</div>
                    @error('params')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
        <div class="col-12" data-target-field="url" @if ($currentTarget !== 'url') hidden @endif>
            <label class="form-label" for="url">Link</label>
            <input class="form-control @error('url') is-invalid @enderror" id="url" name="url" value="{{ old('url', $item->url) }}" placeholder="/reports or https://…">
            @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="permission">Who sees it</label>
            <select class="form-select" id="permission" name="permission">
                <option value="">Everyone who sees the module</option>
                @foreach ($permissions as $permission)
                    <option value="{{ $permission }}" @selected(old('permission', $item->permission) === $permission)>Users with "{{ $permission }}"</option>
                @endforeach
            </select>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="{{ route('navigation-items.index', ['module' => $module->uid]) }}" class="btn btn-sm btn-outline-dark">Cancel</a>
            <button class="btn btn-sm btn-primary">{{ $isNew ? 'Add' : 'Save' }}</button>
        </div>
    </form>
@endsection
