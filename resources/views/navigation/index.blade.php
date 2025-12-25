@extends('userinterface::layouts.app')

@section('content')
<form method="POST" action="{{ route('navigation.save-order') }}" id="navigationForm">
    @csrf

    {{-- Hidden input to store final order --}}
    <input type="hidden" name="order" id="navigationOrderInput">

    <div class="row">
        <div class="col-md-4">

            <div class="d-flex justify-content-between align-items-center mb-1">
                <h5 class="fs-6 text-muted mb-0">Primary Navigation Order</h5>
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    Update
                </button>
            </div>

            <small class="text-muted small">
                Drag and drop modules to reorder navigation
            </small>

            {{-- Unsaved changes warning --}}
            <div id="unsavedWarning" class="alert alert-warning py-2 px-3 small mt-2 d-none">
                <i class="fas fa-exclamation-triangle me-1"></i>
                You have unsaved changes. Please update to apply the new navigation order.
            </div>

            <div class="p-2">
                <ul id="navigationOrder" class="list-group">
                    @foreach($orderedModules as $module)
                        <li class="list-group-item list-group-item-action d-flex justify-content-between align-items-center rounded shadow-sm"
                            draggable="true"
                            data-id="{{ $module->id }}">
                            {{ $module->name }}
                            <i class="fas fa-grip-lines"></i>
                        </li>
                    @endforeach
                </ul>
            </div>

        </div>
    </div>
</form>
@endsection

@push('styles')
<style>
/* Cursor */
#navigationOrder li {
    cursor: grab;
    transition: background-color 0.2s, box-shadow 0.2s;
}

/* Dragging row style */
#navigationOrder li.dragging {
    border: 1px solid var(--bs-primary) !important;
    color: var(--bs-primary) !important;
    cursor: grabbing;
    box-shadow: 0 .25rem .5rem rgba(0,0,0,.1);
}

/* Hover highlight */
#navigationOrder li:hover {
    background-color: var(--bs-light);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('navigationOrder');
    const orderInput = document.getElementById('navigationOrderInput');
    const form = document.getElementById('navigationForm');
    const warning = document.getElementById('unsavedWarning');

    let draggedItem = null;

    // Store initial order
    const initialOrder = Array.from(list.querySelectorAll('li'))
        .map(li => li.dataset.id)
        .join(',');

    function hasChanges() {
        const currentOrder = Array.from(list.querySelectorAll('li'))
            .map(li => li.dataset.id)
            .join(',');

        return currentOrder !== initialOrder;
    }

    function updateWarning() {
        if (hasChanges()) {
            warning.classList.remove('d-none');
        } else {
            warning.classList.add('d-none');
        }
    }

    list.addEventListener('dragstart', function (e) {
        draggedItem = e.target;
        draggedItem.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        const target = e.target.closest('li');
        if (!target || target === draggedItem) return;

        const rect = target.getBoundingClientRect();
        const next = (e.clientY - rect.top) / rect.height > 0.5;
        list.insertBefore(draggedItem, next ? target.nextSibling : target);
    });

    list.addEventListener('dragend', function () {
        if (draggedItem) draggedItem.classList.remove('dragging');
        draggedItem = null;
        updateWarning();
    });

    // On submit → save order
    form.addEventListener('submit', function () {
        const order = Array.from(list.querySelectorAll('li'))
            .map(li => parseInt(li.dataset.id, 10));

        orderInput.value = JSON.stringify(order);
    });
});
</script>
@endpush