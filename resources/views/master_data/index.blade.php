@extends('userinterface::layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="fs-6 text-muted">Total {{ $masterData->count() }} Master Data(s)</h5>
    <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2 shadow-sm rounded @cannot('create-master_data') disabled @endcannot"
        @cannot('create-master_data') disabled @endcannot 
        id="createMasterDataButton" data-bs-toggle="modal" data-bs-target="#createMasterDataModal">
        <i class="fa-regular fa-plus fs-6"></i> Master Data
    </button>
</div>
<!-- Search Filter -->
<div class="input-group mb-2" style="max-width: 300px;">
    <span class="input-group-text"><i class="fas fa-search"></i></span>
    <input type="text" class="form-control" id="treeSearch" placeholder="Filter items...">
</div>

<!-- FIXED: Updated resizable container wrapper -->
<div class="resizable-container p-2 bg-light d-flex" style="height: calc(100vh - 200px);">
    <!-- Left Panel - Tree Structure -->
    <div class="resizable-left" style="width: 50%; min-width: 200px;">
        <!-- Master Data Tree - Scrollable -->
        <div class="overflow-auto h-100 pe-3">
            <div class="tree" style="min-width: max-content;">
                @foreach ($masterData->where('parent_id', 0) as $rootData)
                    @include('foundation::components.inc-with-props.tree-items', [
                        'data' => $rootData, 
                        'masterData' => $masterData, 
                        'level' => 0,
                        'selectedNode' => $selectedNode ?? null
                    ])
                @endforeach
            </div>
        </div>
    </div>

    <!-- FIXED: Resize handle -->
    <div class="resize-handle"></div>

    <!-- Right Panel - Details View -->
    <div class="resizable-right" style="flex: 1; min-width: 200px;">
        <div class="h-100 overflow-y-auto ps-3">
            @if(isset($selectedNode))
                <!-- Parent Node -->
                <div class="mb-4">
                    <h5 class="fs-6 text-muted">Parent Node</h5>
                    @if($parentNode)
                        <div class="d-flex align-items-center justify-content-start gap-2">
                            <a href="{{ route('master-data.show', $parentNode->id) }}">
                                <span class="tree-item-key">{{ $parentNode->key }}</span>
                            </a>
                            <span class="badge badge-{{ $parentNode->status }}">
                                {{ ucfirst($parentNode->status) }}
                            </span>
                        </div>
                        @if($parentNode->value)
                            <span class="text-muted">{{ $parentNode->value }}</span>
                        @endif
                    @else
                        <p class="mb-0">No parent (root node)</p>
                    @endif
                </div>

                <!-- Node Details -->
                <div class="mb-4 border p-2 border-primary rounded">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="fs-6 text-muted">Selected Node</h5>
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <button class="btn btn-sm btn-outline-dark edit-master-data @cannot('edit-master_data') disabled @endcannot"
                                @cannot('edit-master_data') disabled @endcannot
                                data-id="{{ $selectedNode->id }}"
                                data-key="{{ $selectedNode->key }}"
                                data-value="{{ $selectedNode->value }}"
                                data-parent-id="{{ $selectedNode->parent_id }}"
                                data-meta="{{ json_encode($selectedNode->metas->pluck('meta_value', 'meta_key')) }}"
                                data-bs-toggle="modal" 
                                data-bs-target="#editMasterDataModal">
                                <i class="fas fa-edit fa-xs"></i>
                            </button>
                            <form action="{{ route('master-data.destroy', $selectedNode->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger @cannot('delete-master_data') disabled @endcannot"
                                @cannot('delete-master_data') disabled @endcannot onclick="return confirm('Are you sure you want to delete {{$selectedNode?->key}}?')">
                                    <i class="fas fa-trash fa-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-start gap-2">
                        <span><i class="fas fa-fw fa-key"></i>Key: {{ $selectedNode->key ?? 'N/A' }}</span>
                        <span class="badge badge-{{ $selectedNode->status }}">
                            {{ ucfirst($selectedNode->status) }}
                        </span>
                    </div>
                    @if($selectedNode->value)
                        <span><i class="fas fa-fw fa-tag"></i>Value: {{ $selectedNode->value ?? 'N/A' }}</span>
                    @endif
                </div>

                <!-- Meta Data -->
                <div class="mb-4">
                    <h5 class="fs-6 text-muted mb-1">Meta Data ({{ $selectedNode->metas->count() }})</h5>
                    @if($selectedNode->metas->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    @foreach($selectedNode->metas as $meta)
                                    <tr>
                                        <th width="30%">{{ $meta->meta_key }}</th>
                                        <td>{{ $meta->meta_value }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No meta data</p>
                    @endif
                </div>
                
                <!-- Child Nodes with Add Child Button -->
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="fs-6 text-muted">Child Nodes ({{ $childNodes->count() }})</h5>
                        <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 @cannot('create-master_data') disabled @endcannot"
                            @cannot('create-master_data') disabled @endcannot
                            onclick="setParentForCreate({{ $selectedNode->id }})">
                            <i class="fas fa-plus fa-xs"></i> Child
                        </button>
                    </div>
                    @if($childNodes->count() > 0)
                        <div class="overflow-auto border rounded p-2 bg-white" style="max-height: 300px;">
                            @foreach($childNodes as $child)
                            <a href="{{ route('master-data.show', $child->id) }}" 
                                class="mb-2 d-block text-decoration-none">
                                <div class="d-flex align-items-center justify-content-start gap-2">
                                    <span>{{ $child->key }}</span>
                                    <span class="badge badge-{{ $child->status }}">
                                        {{ ucfirst($child->status) }}
                                    </span>
                                </div>
                                @if($child->value)
                                    <span class="text-muted">{{ $child->value }}</span>
                                @endif
                            </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted">No child nodes</p>
                    @endif
                </div>
            @else
                <!-- Empty State -->
                <div class="text-center text-muted py-5">
                    <i class="fas fa-info-circle fa-3x mb-3"></i>
                    <p>Select a node from the tree to view details</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Create Master Data Modal -->
<div class="modal fade" id="createMasterDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6">Create Master Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createMasterDataForm" method="POST" action="{{ route('master-data.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="create_key" class="form-label">Key *</label>
                        <input type="text" class="form-control" id="create_key" name="key" required>
                    </div>
                    <div class="mb-3">
                        <label for="create_value" class="form-label">Value</label>
                        <textarea class="form-control" id="create_value" name="value" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="create_parent_id" class="form-label">Parent Member</label>
                        <select class="form-control" id="create_parent_id" name="parent_id">
                            <option value="0">None (Root Item)</option>
                            @foreach ($masterData->where('parent_id', 0) as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->key }}</option>
                            @endforeach
                        </select>
                    </div>
                    @can('manage-meta')
                    <div class="mb-3">
                        <h5 class="fs-6">Meta Data</h5>
                        <div id="createMetaFields">
                            <div class="row mb-2">
                                <div class="col">
                                    <input type="text" class="form-control" name="meta_keys[]" placeholder="Key">
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control" name="meta_values[]" placeholder="Value">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="addCreateMetaField()">
                            <i class="fas fa-plus me-1"></i> Add More
                        </button>
                    </div>
                    @endcan
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-outline-primary @cannot('create-master_data') disabled @endcannot"
                        @cannot('create-master_data') disabled @endcannot>Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Master Data Modal -->
<div class="modal fade" id="editMasterDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6">Edit Master Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editMasterDataForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_key" class="form-label">Key *</label>
                        <input type="text" class="form-control" id="edit_key" name="key" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_value" class="form-label">Value</label>
                        <textarea class="form-control" id="edit_value" name="value" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_parent_id" class="form-label">Parent Member</label>
                        <select class="form-control" id="edit_parent_id" name="parent_id">
                            <option value="0">None (Root Item)</option>
                            @foreach ($masterData as $parent)
                            <option value="{{ $parent->id }}">{{ $parent->key }}</option>
                            @endforeach
                        </select>
                    </div>
                    @can('manage-meta')
                    <div class="mb-3">
                        <h5 class="fs-6 text-muted">Meta Data</h5>
                        <div id="editMetaFields">
                            <!-- Meta fields will be populated here by JavaScript -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="addEditMetaField()">
                            <i class="fas fa-plus me-1"></i> Add More
                        </button>
                    </div>
                    @endcan
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-outline-primary @cannot('edit-master_data') disabled @endcannot"
                        @cannot('edit-master_data') disabled @endcannot>Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Meta Field Template (hidden) -->
<template id="metaFieldTemplate">
    <div class="row mb-2">
        <div class="col">
            <input type="text" class="form-control" name="meta_keys[]" placeholder="Key">
        </div>
        <div class="col">
            <input type="text" class="form-control" name="meta_values[]" placeholder="Value">
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMetaField(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</template>
@endsection

@push('styles')
<style>
/* FIXED: Updated resizable container styles to prevent horizontal scroll */
.resizable-container {
    position: relative;
    overflow: hidden; /* Prevent horizontal scroll */
}

.resizable-left {
    overflow: hidden; /* Prevent content from overflowing */
}

.resizable-right {
    overflow: hidden; /* Prevent content from overflowing */
}

.resize-handle {
    width: 6px;
    cursor: col-resize;
    background: linear-gradient(90deg, transparent 2px, #dee2e6 2px, #dee2e6 4px, transparent 4px);
    position: relative;
    flex-shrink: 0;
}

.resize-handle:hover {
    background: linear-gradient(90deg, transparent 1px, #0d6efd 1px, #0d6efd 5px, transparent 5px);
}

.resize-handle::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 40px;
    background: transparent;
}

/* Tree structure styling */
.tree, 
.tree ul, 
.tree li {
    list-style: none;
    padding-left: 0;
    margin: 0;
}

/* Root level items - no connectors, no indentation */
.tree > .tree-item {
    position: relative;
    margin-bottom: 4px;
}

/* Root level content styling */
.tree > .tree-item > .tree-item-content {
    padding-left: 0;
    font-weight: 500;
}

.tree-item-content {
    transition: all 0.2s;
    padding: 4px 8px;
    border-radius: 4px;
}

/* Toggle functionality */
.tree-item-toggle {
    margin-right: 8px;
    cursor: pointer;
    color: #6c757d;
    width: 18px;
    text-align: center;
    display: inline-block;
}

/* Children container */
.tree-item-children {
    padding-left: 0;
    margin-left: 25px;
    position: relative;
    display: none; /* Hidden by default */
    margin-top: 4px;
}

.tree-item-expanded > .tree-item-children {
    display: block;
}

/* Child items */
.tree-item-children .tree-item {
    position: relative;
    margin-bottom: 2px;
}

.tree-item-children .tree-item > .tree-item-content {
    padding-left: 25px !important;
    font-weight: normal;
}

.tree-item-value {
    color: #6c757d;
    margin-left: 6px;
    font-weight: normal;
}

/* Vertical line from parent */
.tree-item-children::before {
    content: "";
    position: absolute;
    left: 0;
    top: -4px;
    bottom: 20px;
    width: 1px;
    background-color: #dee2e6;
    border-left: 1px solid #adb5bd;
}

/* Horizontal connectors for children */
.tree-item-children .tree-item::before {
    content: "";
    position: absolute;
    left: 0;
    top: 18px;
    width: 20px;
    height: 1px;
    background-color: #adb5bd;
    border-top: 1px solid #adb5bd;
}

/* L-connector for last child */
.tree-item-children .tree-item:last-child::after {
    content: "";
    position: absolute;
    left: 0;
    top: 18px;
    bottom: -10px;
    width: 1px;
    background-color: #f8f9fa;
    border-left: 1px solid #f8f9fa;
    z-index: 1;
}

/* Nested children (third level and beyond) */
.tree-item-children .tree-item-children {
    margin-left: 25px;
}

/* Selected state */
.tree-item-content.selected {
    background-color: #e3f2fd;
    border-left: 3px solid #2196f3;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // FIXED: Updated resize functionality with better constraints
    let isResizing = false;
    
    $('.resize-handle').mousedown(function(e) {
        isResizing = true;
        $('body').css('user-select', 'none'); // Prevent text selection during resize
        
        $(document).mousemove(function(e) {
            if (!isResizing) return;
            
            const container = $('.resizable-container');
            const containerOffset = container.offset().left;
            const containerWidth = container.width();
            const mouseX = e.pageX - containerOffset;
            
            // Calculate the percentage, accounting for the resize handle width (6px)
            const handleWidth = 6;
            const availableWidth = containerWidth - handleWidth;
            const leftWidth = ((mouseX - handleWidth/2) / availableWidth) * 100;
            
            // Constrain between 20% and 80% to prevent horizontal scrolling
            if (leftWidth >= 20 && leftWidth <= 80) {
                $('.resizable-left').css('width', leftWidth + '%');
            }
        });
        
        $(document).mouseup(function() {
            isResizing = false;
            $('body').css('user-select', ''); // Restore text selection
            $(document).off('mousemove mouseup');
        });
        
        e.preventDefault(); // Prevent default drag behavior
    });

    // Function to save expanded state to sessionStorage
    function saveTreeState() {
        const expandedNodes = [];
        $('.tree-item-expanded').each(function() {
            const nodeId = $(this).data('id');
            if (nodeId) {
                expandedNodes.push(nodeId);
            }
        });
        sessionStorage.setItem('treeExpandedNodes', JSON.stringify(expandedNodes));
    }

    // Function to restore expanded state from sessionStorage
    function restoreTreeState() {
        const expandedNodes = JSON.parse(sessionStorage.getItem('treeExpandedNodes') || '[]');
        
        expandedNodes.forEach(function(nodeId) {
            const $item = $(`.tree-item[data-id="${nodeId}"]`);
            if ($item.length) {
                $item.addClass('tree-item-expanded');
                $item.find('> .tree-item-content .tree-item-toggle i')
                    .removeClass('fa-chevron-right')
                    .addClass('fa-chevron-down');
            }
        });
    }

    // Function to expand path to selected node
    function expandToSelectedNode() {
        const $selectedNode = $('.tree-item-content.selected').closest('.tree-item');
        if ($selectedNode.length) {
            // Expand all parent nodes
            $selectedNode.parents('.tree-item').each(function() {
                const $item = $(this);
                $item.addClass('tree-item-expanded');
                $item.find('> .tree-item-content .tree-item-toggle i')
                    .removeClass('fa-chevron-right')
                    .addClass('fa-chevron-down');
            });
            saveTreeState(); // Save the state after expanding to selected
        }
    }

    // Restore tree state on page load
    restoreTreeState();
    
    // Always expand to show the selected node
    expandToSelectedNode();

    // Tree toggle functionality with state saving
    $(document).on('click', '.tree-item-toggle', function(e) {
        e.stopPropagation();
        const $item = $(this).closest('.tree-item');
        $item.toggleClass('tree-item-expanded');
        
        const $icon = $(this).find('i');
        if ($item.hasClass('tree-item-expanded')) {
            $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        } else {
            $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        }
        
        // Save state after toggle
        saveTreeState();
    });
    
    // Save state before navigating to a node
    $(document).on('click', '.tree-item-content a', function(e) {
        saveTreeState();
    });

    // Clear tree state when explicitly requested (optional)
    window.clearTreeState = function() {
        sessionStorage.removeItem('treeExpandedNodes');
    };
    
    // Search functionality with state preservation
    $('#treeSearch').on('keyup', function() {
        const searchText = $(this).val().toLowerCase();
        
        if (searchText.length === 0) {
            $('.tree-item').show();
            restoreTreeState(); // Restore original state when search is cleared
            expandToSelectedNode(); // Make sure selected node is still visible
            return;
        }
        
        $('.tree-item').each(function() {
            const $item = $(this);
            const text = $item.text().toLowerCase();
            
            if (text.includes(searchText)) {
                $item.show();
                $item.parents('.tree-item').show().addClass('tree-item-expanded')
                    .find('> .tree-item-content .tree-item-toggle i')
                    .removeClass('fa-chevron-right').addClass('fa-chevron-down');
            } else {
                $item.hide();
            }
        });
    });

    // Edit modal setup
    $(document).on('click', '.edit-master-data', function() {
        const id = $(this).data('id');
        const key = $(this).data('key');
        const value = $(this).data('value');
        const parentId = $(this).data('parent-id');
        const meta = $(this).data('meta');
        
        // Set form action
        $('#editMasterDataForm').attr('action', `/master-data/${id}`);
        
        // Populate fields
        $('#edit_key').val(key);
        $('#edit_value').val(value);
        $('#edit_parent_id').val(parentId);
        
        // Clear and populate meta fields
        $('#editMetaFields').empty();
        if (meta && Object.keys(meta).length > 0) {
            Object.entries(meta).forEach(([metaKey, metaValue]) => {
                addEditMetaField(metaKey, metaValue);
            });
        } else {
            addEditMetaField(); // Add one empty field
        }
    });

    // Set parent for new child (with disabled field, hidden input, and display name)
    window.setParentForCreate = function(parentId) {
        // Set the select value and disable it
        $('#create_parent_id').val(parentId).prop('disabled', true);
        
        // Remove existing hidden field and display element if any
        $('#create_parent_id_hidden').remove();
        $('#create_parent_display').remove();
        
        // Add a hidden field with the same name to carry the value
        $('#create_parent_id').after('<input type="hidden" id="create_parent_id_hidden" name="parent_id" value="' + parentId + '">');
        
        // Get the parent name from the tree or from the select option
        let parentName = '';
        
        // First try to get from select option
        const selectOption = $('#create_parent_id option[value="' + parentId + '"]');
        if (selectOption.length && selectOption.text().trim() !== '') {
            parentName = selectOption.text();
        } else {
            // If not found in select, get from the tree structure
            const treeItem = $('.tree-item[data-id="' + parentId + '"]');
            if (treeItem.length) {
                parentName = treeItem.find('.tree-item-key').first().text().trim();
            }
            
            // If still not found, try to get from the current selected node if it matches
            if (!parentName) {
                const currentSelectedKey = $('.tree-item-content.selected .tree-item-key').text().trim();
                const currentSelectedId = $('.tree-item-content.selected').closest('.tree-item').data('id');
                if (currentSelectedId == parentId) {
                    parentName = currentSelectedKey;
                }
            }
        }
        
        // Fallback if still no name found
        if (!parentName) {
            parentName = 'Selected Item';
        }
        
        // Add a display element to show the selected parent
        $('#create_parent_id').after('<div id="create_parent_display" class="form-control bg-light text-muted" style="margin-top: 5px;">' + parentName + '</div>');
        
        // Hide the original select since it's disabled
        $('#create_parent_id').hide();
        
        $('#createMasterDataModal').modal('show');
    };

    // Reset parent selection when create modal is hidden
    $('#createMasterDataModal').on('hidden.bs.modal', function() {
        $('#create_parent_id').val(0).prop('disabled', false).show(); // Show the select again
        $('#create_parent_id_hidden').remove(); // Remove the hidden field
        $('#create_parent_display').remove(); // Remove the display element
    });

    // Add meta field to create form
    window.addCreateMetaField = function(key = '', value = '') {
        const template = $('#metaFieldTemplate').html();
        $('#createMetaFields').append(template);
        const lastRow = $('#createMetaFields .row').last();
        lastRow.find('input[name="meta_keys[]"]').val(key);
        lastRow.find('input[name="meta_values[]"]').val(value);
    };

    // Add meta field to edit form
    window.addEditMetaField = function(key = '', value = '') {
        const template = $('#metaFieldTemplate').html();
        $('#editMetaFields').append(template);
        const lastRow = $('#editMetaFields .row').last();
        lastRow.find('input[name="meta_keys[]"]').val(key);
        lastRow.find('input[name="meta_values[]"]').val(value);
    };

    // Remove meta field
    window.removeMetaField = function(button) {
        $(button).closest('.row').remove();
    };
});
</script>
@endpush