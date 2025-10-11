@php
    $layout = class_exists(\Iquesters\UserInterface\UserInterfaceServiceProvider::class)
        ? 'userinterface::layouts.app'
        : config('product.layout');
@endphp

@extends($layout)

@section('content')
<div>
    <h5 class="mb-2 fs-6 text-muted">Entities</h5>

    {{-- Dropdown & button --}}
    <div class="d-flex align-items-center gap-2 mb-3">
        <select id="entitySelect" class="form-select" style="width: 250px;">
            <option value="">-- Select Entity --</option>
            @foreach ($entities as $entity)
                <option value="{{ $entity->id }}">{{ ucfirst($entity->entity_name) }}</option>
            @endforeach
        </select>

        <button id="showEntityBtn" class="btn btn-sm btn-primary">Show</button>
    </div>

    {{-- Display area --}}
    <div id="entityDetails" style="display:none;">
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
    </div>
</div>

<script>
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
</script>
@endsection