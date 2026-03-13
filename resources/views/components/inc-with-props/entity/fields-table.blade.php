@props([
    'fields' => [],
    'systemFields' => [],
    'editable' => false,
    'showSystemFields' => false,
])

<div class="table-responsive">
    <table class="table table-bordered table-striped mb-0">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Field</th>
                <th>Type</th>
                <th>Label</th>
                <th>Required</th>
                <th>Nullable</th>
                <th>Input</th>
                <th>Default</th>
                @if($editable)
                    <th>Actions</th>
                @endif
            </tr>
        </thead>

        <tbody id="{{ $editable ? 'fieldsMainTableBody' : '' }}">
            @forelse($fields as $index => $field)
                @php
                    $isSystem = in_array($field['name'], $systemFields);
                @endphp

                {{-- Hide system fields unless explicitly allowed --}}
                @if($isSystem && !$showSystemFields)
                    @continue
                @endif

                <tr class="{{ $isSystem ? 'system-field table-secondary' : '' }}">
                    <td>{{ $loop->iteration }}</td>

                    {{-- FIELD NAME --}}
                    <td>
                        @if($editable && !$isSystem)
                            <input type="text"
                                   class="form-control form-control-sm"
                                   name="fields[{{ $index }}][name]"
                                   value="{{ $field['name'] }}"
                                   required>
                        @else
                            <code>{{ $field['name'] }}</code>
                        @endif
                    </td>

                    {{-- TYPE --}}
                    <td>
                        @if($editable && !$isSystem)
                            <select class="form-select form-select-sm"
                                    name="fields[{{ $index }}][type]"
                                    required>
                                @foreach(['string','text','longtext','integer','decimal','boolean','date','datetime','time'] as $type)
                                    <option value="{{ $type }}" {{ $field['type'] === $type ? 'selected' : '' }}>
                                        {{ ucfirst($type) }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            {{ $field['type'] }}
                        @endif
                    </td>

                    {{-- LABEL --}}
                    <td>
                        @if($editable && !$isSystem)
                            <input type="text"
                                   class="form-control form-control-sm"
                                   name="fields[{{ $index }}][label]"
                                   value="{{ $field['label'] }}"
                                   required>
                        @else
                            {{ $field['label'] }}
                        @endif
                    </td>

                    {{-- REQUIRED --}}
                    <td>
                        @if($editable && !$isSystem)
                            <select class="form-select form-select-sm"
                                    name="fields[{{ $index }}][required]">
                                <option value="1" {{ $field['required'] ? 'selected' : '' }}>YES</option>
                                <option value="0" {{ !$field['required'] ? 'selected' : '' }}>NO</option>
                            </select>
                        @else
                            {{ $field['required'] ? 'YES' : 'NO' }}
                        @endif
                    </td>

                    {{-- NULLABLE --}}
                    <td>
                        @if($editable && !$isSystem)
                            <select class="form-select form-select-sm"
                                    name="fields[{{ $index }}][nullable]">
                                <option value="1" {{ $field['nullable'] ? 'selected' : '' }}>YES</option>
                                <option value="0" {{ !$field['nullable'] ? 'selected' : '' }}>NO</option>
                            </select>
                        @else
                            {{ $field['nullable'] ? 'YES' : 'NO' }}
                        @endif
                    </td>

                    {{-- INPUT TYPE --}}
                    <td>
                        @if($editable && !$isSystem)
                            <select class="form-select form-select-sm"
                                    name="fields[{{ $index }}][input_type]">
                                @foreach(['text','textarea','number','email','date','datetime-local','time','checkbox','select'] as $input)
                                    <option value="{{ $input }}" {{ $field['input_type'] === $input ? 'selected' : '' }}>
                                        {{ ucfirst($input) }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            {{ $field['input_type'] }}
                        @endif
                    </td>

                    {{-- DEFAULT --}}
                    <td>
                        @if($editable && !$isSystem)
                            <input type="text"
                                   class="form-control form-control-sm"
                                   name="fields[{{ $index }}][default]"
                                   value="{{ $field['default'] ?? '' }}">
                        @else
                            {{ $field['default'] ?? '—' }}
                        @endif
                    </td>

                    {{-- ACTIONS --}}
                    @if($editable && !$isSystem)
                        <td>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="removeRow(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr class="no-data-row">
                    <td colspan="{{ $editable ? 9 : 8 }}" class="text-center text-muted">
                        No fields defined.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- FOOTER ACTION / LOCK MESSAGE --}}
@if($editable)
    <div class="p-2">
        <button type="button"
                class="btn btn-sm btn-outline-primary"
                onclick="addPrimaryField()">
            <i class="fas fa-plus"></i> Add Field
        </button>
    </div>
@else
    <div class="alert alert-warning mt-2 mb-0">
        <i class="fas fa-lock me-1"></i>
        This entity is <strong>published</strong>. Primary fields are locked and cannot be modified.
    </div>
@endif
