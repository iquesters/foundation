@extends('userinterface::layouts.app')

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make(['Business Entities']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('List of business entities'))

@php
    use Iquesters\Foundation\Services\BusinessEntitySchemaGenerator as Screens;

    $tabs = [
        ['route' => 'business-entities.index', 'params' => [], 'icon' => 'fas fa-fw fa-diagram-project', 'label' => 'Business Entities'],
    ];
@endphp

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div>
            <h5 class="fs-6 text-muted mb-0">{{ $businessEntities->count() }} {{ \Illuminate\Support\Str::plural('business entity', $businessEntities->count()) }}</h5>
            <div class="small text-muted">Each one saves a record across several entities, e.g. a user with their roles.</div>
        </div>
        <a href="{{ route('business-entities.create') }}" class="btn btn-sm btn-outline-primary">
            <i class="fa-regular fa-fw fa-plus"></i><span class="d-none d-md-inline-block ms-1">Business entity</span>
        </a>
    </div>

    @if ($businessEntities->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="fas fa-diagram-project fa-2x mb-3 d-block"></i>
            <p class="mb-1 fw-medium">No business entities yet</p>
            <p class="small mb-3">
                Combine entities into one record with one form, for example a customer with their address and notes,
                or a user with their roles. No tables are created: you only describe how existing tables relate.
            </p>
            <a href="{{ route('business-entities.create') }}" class="btn btn-sm btn-primary">
                <i class="fa-regular fa-fw fa-plus"></i><span class="ms-1">Create your first business entity</span>
            </a>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="d-none d-md-table-cell">Entities</th>
                        <th class="d-none d-lg-table-cell">Appears as</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($businessEntities as $businessEntity)
                        @php
                            $parts = collect($businessEntity->definition['parts'] ?? []);
                            $meta = fn (string $key) => $businessEntity->metas->firstWhere('meta_key', $key)?->meta_value;
                            $tableSchemaUid = $meta('table_schema_uid');
                            $output = in_array($meta('output'), Screens::OUTPUTS, true) ? $meta('output') : Screens::OUTPUT_FORM_TABLE;
                            $usedIn = $meta('use_in_table');
                            $appearsAs = match ($output) {
                                Screens::OUTPUT_API => 'API only',
                                Screens::OUTPUT_FORM => 'Form only',
                                default => 'Own list and form',
                            };
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('business-entities.edit', $businessEntity->uid) }}" class="text-decoration-none">{{ $businessEntity->name }}</a>
                                <div class="small text-muted">{{ $businessEntity->desc ?: $businessEntity->slug }}</div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                @foreach ($parts as $index => $part)
                                    <span class="badge {{ $index === 0 ? 'text-bg-primary' : 'text-bg-light border' }}" title="{{ $index === 0 ? 'Primary entity' : str_replace('_', ' ', $part['link']['type'] ?? '') }}">{{ $part['label'] ?? $part['alias'] }}</span>
                                @endforeach
                            </td>
                            <td class="d-none d-lg-table-cell small">
                                {{ $appearsAs }}
                                @if ($usedIn)
                                    <div class="text-muted">used in the {{ $usedIn }} table</div>
                                @endif
                            </td>
                            <td><x-userinterface::status :status="$businessEntity->status" /></td>
                            <td>
                                <div class="d-flex justify-content-end gap-2">
                                    @if ($tableSchemaUid && Route::has('ui.list'))
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('ui.list', $tableSchemaUid) }}" title="Open its records">
                                            <i class="fas fa-fw fa-table"></i><span class="d-none d-xl-inline ms-1">Records</span>
                                        </a>
                                    @endif
                                    <a class="btn btn-sm btn-outline-dark" href="{{ route('business-entities.edit', $businessEntity->uid) }}" title="Edit">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </a>
                                    <form action="{{ route('business-entities.destroy', $businessEntity->uid) }}" method="POST"
                                        data-confirm="Delete &quot;{{ $businessEntity->name }}&quot;?&#10;&#10;Its form and list are removed{{ $usedIn ? ', and the '.$usedIn.' table gets its own form back' : '' }}. The records themselves stay in their tables."
                                        onsubmit="return confirm(this.dataset.confirm)">
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
