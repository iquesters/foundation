@extends('userinterface::layouts.app')

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make(['Entity']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('List of Entities'))

@php
    $tabs = [
        [
            'route' => 'entities.index',
            'params' => [],
            'icon' => 'fas fa-fw fa-cube',
            'label' => 'Entity',
        ],
    ];
@endphp

@section('content')
<div>
    {{-- <h5 class="mb-2 fs-6 text-muted">Entities</h5> --}}

    {{-- Dropdown & button --}}
    {{-- <div class="d-flex align-items-center gap-2 mb-3">
        <select id="entitySelect" class="form-select" style="width: 250px;">
            <option value="">-- Select Entity --</option>
            @foreach ($entities as $entity)
                <option value="{{ $entity->id }}">{{ ucfirst($entity->entity_name) }}</option>
            @endforeach
        </select>

        <button id="showEntityBtn" class="btn btn-sm btn-primary">Show</button>
    </div> --}}

    {{-- Display area --}}
    {{-- <div id="entityDetails" style="display:none;">
        <h4 id="entityTitle" class="mb-3"></h4>

        <div class="mb-3">
            <h5 class="fs-6 text-muted">Fields</h5>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Label</th>
                    </tr>
                </thead>
                <tbody id="fieldsTable"></tbody>
            </table>
        </div>

        <div>
            <h5 class="fs-6 text-muted">Meta Fields</h5>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Meta Key</th>
                    </tr>
                </thead>
                <tbody id="metaFieldsTable"></tbody>
            </table>
        </div>
    </div> --}}
</div>

{{-- <script>
    const entities = @json($entities);

    document.getElementById('showEntityBtn').addEventListener('click', () => {
        const selectedId = document.getElementById('entitySelect').value;
        const entity = entities.find(e => e.id == selectedId);

        if (!entity) {
            document.getElementById('entityDetails').style.display = 'none';
            return;
        }

        document.getElementById('entityTitle').textContent = entity.entity_name;

        // Populate fields
        const fieldsTable = document.getElementById('fieldsTable');
        fieldsTable.innerHTML = '';
        for (const [key, field] of Object.entries(entity.fields || {})) {
            fieldsTable.innerHTML += `
                <tr>
                    <td>${field.name}</td>
                    <td>${field.type}</td>
                    <td>${field.label}</td>
                </tr>
            `;
        }

        // Populate meta fields
        const metaFieldsTable = document.getElementById('metaFieldsTable');
        metaFieldsTable.innerHTML = '';
        for (const [key, meta] of Object.entries(entity.meta_fields || {})) {
            metaFieldsTable.innerHTML += `<tr><td>${meta.meta_key}</td></tr>`;
        }

        document.getElementById('entityDetails').style.display = 'block';
    });
</script> --}}

<div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="fs-6 text-muted">Total {{ $entities->count() }} Entities</h5>
        <a href="{{ route('entities.create') }}" class="btn btn-sm btn-outline-primary">
            <i class="fa-regular fa-fw fa-plus"></i><span class="d-none d-md-inline-block ms-1">Entitity</span>
        </a>
    </div>
    <div class="">
        <div class="table-responsive">
            <table id="entities-table" class="table table-striped table-hover">
                <thead>
                    <tr>
                        {{-- <th>UID</th> --}}
                        <th>Name</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entities as $entity)
                    <tr>
                        {{-- <td>{{ $organisation->id }}</td> --}}
                        <td>
                            <a href="{{ route('entities.show', $entity->uid) }}" 
                                class="text-decoration-none">
                                {{ $entity->entity_name }}
                            </a>
                            <br>
                            <small><small class="text-muted">{{ $entity->uid }}</small></small>
                        </td>
                        <td>
                            <x-userinterface::status :status="$entity->status" />
                        </td>
                        <td>{{ $entity->created_at->format('d M Y') }}</td>
                        <td>
                            <div class="d-flex align-content-center justify-content-center gap-2">
                                {{-- <li>
                                    <a class="dropdown-item text-info" href="{{ route('organisations.show', $organisation->uid) }}">
                                        <i class="fas fa-fw fa-eye me-1"></i> View
                                    </a>
                                </li> --}}
                                    
                                    <a class="btn btn-sm btn-outline-dark" href="{{ route('entities.edit', $entity->uid) }}">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </a>
                                
                                
                                    <form action="{{ route('entities.destroy', $entity->uid) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
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
    </div>
</div>
@endsection


@push('scripts')
<script>
    $(document).ready(function() {
        $('#entities-table').DataTable({
            responsive: true,
            order: [[2, 'desc']]
        });
    });
</script>
@endpush