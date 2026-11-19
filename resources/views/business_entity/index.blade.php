@extends('userinterface::layouts.app')

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make(['Business Entities']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('List of business entities'))

@php
    $tabs = [
        ['route' => 'business-entities.index', 'params' => [], 'icon' => 'fas fa-fw fa-diagram-project', 'label' => 'Business Entities'],
    ];
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <h5 class="fs-6 text-muted mb-0">Total {{ $businessEntities->count() }} business entities</h5>
        <a href="{{ route('business-entities.create') }}" class="btn btn-sm btn-outline-primary">
            <i class="fa-regular fa-fw fa-plus"></i><span class="d-none d-md-inline-block ms-1">Business entity</span>
        </a>
    </div>

    @if ($businessEntities->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="fas fa-diagram-project fa-2x mb-2 d-block"></i>
            No business entities yet. A business entity saves one record across several entities, e.g. a customer with its address.
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="d-none d-md-table-cell">Parts</th>
                        <th class="d-none d-lg-table-cell">Module</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($businessEntities as $businessEntity)
                        @php
                            $parts = collect($businessEntity->definition['parts'] ?? []);
                            $tableSchemaUid = $businessEntity->metas->firstWhere('meta_key', 'table_schema_uid')?->meta_value;
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('business-entities.edit', $businessEntity->uid) }}" class="text-decoration-none">{{ $businessEntity->name }}</a>
                                <div class="small text-muted">{{ $businessEntity->slug }}</div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                @foreach ($parts as $part)
                                    <span class="badge text-bg-light border">{{ $part['alias'] }}@if (! empty($part['link'])) <span class="text-muted">· {{ str_replace('_', ' ', $part['link']['type']) }}</span>@endif</span>
                                @endforeach
                            </td>
                            <td class="d-none d-lg-table-cell">{{ $businessEntity->module?->name ?? '—' }}</td>
                            <td><x-userinterface::status :status="$businessEntity->status" /></td>
                            <td>
                                <div class="d-flex justify-content-end gap-2">
                                    @if ($tableSchemaUid && Route::has('ui.list'))
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('ui.list', $tableSchemaUid) }}" title="Records">
                                            <i class="fas fa-fw fa-table"></i><span class="d-none d-xl-inline ms-1">Records</span>
                                        </a>
                                    @endif
                                    <a class="btn btn-sm btn-outline-dark" href="{{ route('business-entities.edit', $businessEntity->uid) }}" title="Edit">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </a>
                                    <form action="{{ route('business-entities.destroy', $businessEntity->uid) }}" method="POST"
                                        onsubmit="return confirm('Delete this business entity? Its records stay in their tables.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fas fa-fw fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
