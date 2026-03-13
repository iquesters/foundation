@props([
    'metaFields' => [],
    'editable' => false
])

<div class="table-responsive">
    <table class="table table-bordered table-striped mb-0">
        <thead class="table-light">
            <tr>
                <th>#</th><th>Meta Key</th><th>Type</th><th>Label</th>
                <th>Required</th><th>Nullable</th><th>Input{{ $editable ? ' Type' : '' }}</th><th>Display</th>
                @if($editable)
                    <th>Actions</th>
                @endif
            </tr>
        </thead>
        <tbody id="{{ $editable ? 'metaFieldsTableBody' : '' }}">
            @forelse($metaFields as $index => $field)
                <tr data-index="{{ $index }}">
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        @if($editable)
                            <input type="text" class="form-control form-control-sm" 
                                   name="meta_fields[{{ $index }}][meta_key]" 
                                   value="{{ $field['meta_key'] ?? '' }}" required>
                        @else
                            <code>{{ $field['meta_key'] ?? $index }}</code>
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <select class="form-select form-select-sm" name="meta_fields[{{ $index }}][type]" required>
                                <option value="string" {{ ($field['type'] ?? '') == 'string' ? 'selected' : '' }}>String</option>
                                <option value="text" {{ ($field['type'] ?? '') == 'text' ? 'selected' : '' }}>Text</option>
                                <option value="longtext" {{ ($field['type'] ?? '') == 'longtext' ? 'selected' : '' }}>Longtext</option>
                                <option value="integer" {{ ($field['type'] ?? '') == 'integer' ? 'selected' : '' }}>Integer</option>
                                <option value="decimal" {{ ($field['type'] ?? '') == 'decimal' ? 'selected' : '' }}>Decimal</option>
                                <option value="boolean" {{ ($field['type'] ?? '') == 'boolean' ? 'selected' : '' }}>Boolean</option>
                                <option value="date" {{ ($field['type'] ?? '') == 'date' ? 'selected' : '' }}>Date</option>
                                <option value="datetime" {{ ($field['type'] ?? '') == 'datetime' ? 'selected' : '' }}>DateTime</option>
                                <option value="time" {{ ($field['type'] ?? '') == 'time' ? 'selected' : '' }}>Time</option>
                            </select>
                        @else
                            {{ $field['type'] ?? '—' }}
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <input type="text" class="form-control form-control-sm" 
                                   name="meta_fields[{{ $index }}][label]" 
                                   value="{{ $field['label'] ?? '' }}" required>
                        @else
                            {{ $field['label'] ?? '—' }}
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <select class="form-select form-select-sm" name="meta_fields[{{ $index }}][required]">
                                <option value="1" {{ !empty($field['required']) ? 'selected' : '' }}>YES</option>
                                <option value="0" {{ empty($field['required']) ? 'selected' : '' }}>NO</option>
                            </select>
                        @else
                            {{ !empty($field['required']) ? 'YES' : 'NO' }}
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <select class="form-select form-select-sm" name="meta_fields[{{ $index }}][nullable]">
                                <option value="1" {{ !empty($field['nullable']) ? 'selected' : '' }}>YES</option>
                                <option value="0" {{ empty($field['nullable']) ? 'selected' : '' }}>NO</option>
                            </select>
                        @else
                            {{ !empty($field['nullable']) ? 'YES' : 'NO' }}
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <select class="form-select form-select-sm" name="meta_fields[{{ $index }}][input_type]" required>
                                <option value="text" {{ ($field['input_type'] ?? 'text') == 'text' ? 'selected' : '' }}>Text</option>
                                <option value="textarea" {{ ($field['input_type'] ?? '') == 'textarea' ? 'selected' : '' }}>Textarea</option>
                                <option value="number" {{ ($field['input_type'] ?? '') == 'number' ? 'selected' : '' }}>Number</option>
                                <option value="email" {{ ($field['input_type'] ?? '') == 'email' ? 'selected' : '' }}>Email</option>
                                <option value="date" {{ ($field['input_type'] ?? '') == 'date' ? 'selected' : '' }}>Date</option>
                                <option value="datetime-local" {{ ($field['input_type'] ?? '') == 'datetime-local' ? 'selected' : '' }}>Datetime-local</option>
                                <option value="time" {{ ($field['input_type'] ?? '') == 'time' ? 'selected' : '' }}>Time</option>
                                <option value="select" {{ ($field['input_type'] ?? '') == 'select' ? 'selected' : '' }}>Select</option>
                                <option value="checkbox" {{ ($field['input_type'] ?? '') == 'checkbox' ? 'selected' : '' }}>Checkbox</option>
                            </select>
                        @else
                            {{ $field['input_type'] ?? 'text' }}
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <select class="form-select form-select-sm" name="meta_fields[{{ $index }}][display]">
                                <option value="1" {{ ($field['display'] ?? true) ? 'selected' : '' }}>YES</option>
                                <option value="0" {{ !($field['display'] ?? true) ? 'selected' : '' }}>NO</option>
                            </select>
                        @else
                            {{ ($field['display'] ?? true) ? 'YES' : 'NO' }}
                        @endif
                    </td>
                    @if($editable)
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
                        No meta fields{{ $editable ? '. Click "Add Meta Field" to create one.' : '' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($editable)
    <div class="p-2">
        <button type="button" class="btn btn-sm btn-outline-success" onclick="addMetaField()">
            <i class="fas fa-plus"></i> Meta Field
        </button>
    </div>
@endif
