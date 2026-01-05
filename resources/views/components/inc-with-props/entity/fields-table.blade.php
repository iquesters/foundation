@props([
    'fields' => [],
    'systemFields' => [],
    'editable' => false
])

<div class="table-responsive">
    <table class="table table-bordered table-striped mb-0">
        <thead class="table-light">
            <tr>
                <th>#</th><th>Field{{ $editable ? ' Name' : '' }}</th><th>Type</th><th>Label</th>
                <th>Required</th><th>Nullable</th><th>Input{{ $editable ? ' Type' : '' }}</th><th>Default</th>
                @if($editable)
                    <th>Actions</th>
                @endif
            </tr>
        </thead>
        <tbody id="{{ $editable ? 'fieldsMainTableBody' : '' }}">
            @forelse($fields as $index => $field)
                @php $isSystem = in_array($field['name'], $systemFields); @endphp
                @if($editable && $isSystem)
                    @continue
                @endif
                <tr class="{{ $isSystem ? 'system-field' : '' }}" data-index="{{ $index }}">
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        @if($editable)
                            <input type="text" class="form-control form-control-sm" 
                                   name="fields[{{ $index }}][name]" 
                                   value="{{ $field['name'] }}" required>
                        @else
                            <code>{{ $field['name'] }}</code>
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <select class="form-select form-select-sm" name="fields[{{ $index }}][type]" required>
                                <option value="string" {{ $field['type'] == 'string' ? 'selected' : '' }}>String</option>
                                <option value="text" {{ $field['type'] == 'text' ? 'selected' : '' }}>Text</option>
                                <option value="integer" {{ $field['type'] == 'integer' ? 'selected' : '' }}>Integer</option>
                                <option value="decimal" {{ $field['type'] == 'decimal' ? 'selected' : '' }}>Decimal</option>
                                <option value="boolean" {{ $field['type'] == 'boolean' ? 'selected' : '' }}>Boolean</option>
                                <option value="date" {{ $field['type'] == 'date' ? 'selected' : '' }}>Date</option>
                                <option value="datetime" {{ $field['type'] == 'datetime' ? 'selected' : '' }}>DateTime</option>
                            </select>
                        @else
                            {{ $field['type'] }}
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <input type="text" class="form-control form-control-sm" 
                                   name="fields[{{ $index }}][label]" 
                                   value="{{ $field['label'] }}" required>
                        @else
                            {{ $field['label'] }}
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <select class="form-select form-select-sm" name="fields[{{ $index }}][required]">
                                <option value="1" {{ $field['required'] ? 'selected' : '' }}>YES</option>
                                <option value="0" {{ !$field['required'] ? 'selected' : '' }}>NO</option>
                            </select>
                        @else
                            {{ $field['required'] ? 'YES' : 'NO' }}
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <select class="form-select form-select-sm" name="fields[{{ $index }}][nullable]">
                                <option value="1" {{ $field['nullable'] ? 'selected' : '' }}>YES</option>
                                <option value="0" {{ !$field['nullable'] ? 'selected' : '' }}>NO</option>
                            </select>
                        @else
                            {{ $field['nullable'] ? 'YES' : 'NO' }}
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <select class="form-select form-select-sm" name="fields[{{ $index }}][input_type]" required>
                                <option value="text" {{ $field['input_type'] == 'text' ? 'selected' : '' }}>Text</option>
                                <option value="textarea" {{ $field['input_type'] == 'textarea' ? 'selected' : '' }}>Textarea</option>
                                <option value="number" {{ $field['input_type'] == 'number' ? 'selected' : '' }}>Number</option>
                                <option value="email" {{ $field['input_type'] == 'email' ? 'selected' : '' }}>Email</option>
                                <option value="date" {{ $field['input_type'] == 'date' ? 'selected' : '' }}>Date</option>
                                <option value="checkbox" {{ $field['input_type'] == 'checkbox' ? 'selected' : '' }}>Checkbox</option>
                                <option value="select" {{ $field['input_type'] == 'select' ? 'selected' : '' }}>Select</option>
                            </select>
                        @else
                            {{ $field['input_type'] }}
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <input type="text" class="form-control form-control-sm" 
                                   name="fields[{{ $index }}][default]" 
                                   value="{{ $field['default'] ?? '' }}">
                        @else
                            {{ $field['default'] ?? '—' }}
                        @endif
                    </td>
                    @if($editable && !$isSystem)
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr class="no-data-row">
                    <td colspan="{{ $editable ? 9 : 8 }}" class="text-center text-muted">
                        No fields defined{{ $editable ? '. Click "Add Field" to create one.' : '' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($editable)
    <div class="p-2">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPrimaryField()">
            <i class="fas fa-plus"></i> Field
        </button>
    </div>
@endif