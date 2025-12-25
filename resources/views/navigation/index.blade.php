@extends('userinterface::layouts.app')

@section('content')
<form method="POST" id="navigationForm" action="{{ route('navigation.save-order') }}">
    @csrf

    {{-- PRIMARY NAV --}}
    <input type="hidden" name="order" id="navigationOrderInput">

    <div class="row d-flex align-items-start justify-content-between">
        <div class="col-md-5">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h5 class="fs-6 text-muted mb-0">Primary Navigation Order</h5>
                <button type="button" id="savePrimaryNav" class="btn btn-sm btn-outline-primary">
                    Update Primary
                </button>
            </div>

            <small class="text-muted small">
                Drag and drop modules to reorder navigation
            </small>

            <div id="unsavedPrimary" class="alert alert-warning py-2 px-3 small mt-2 d-none">
                <i class="fas fa-exclamation-triangle me-1"></i>
                You have unsaved changes.
            </div>

            <div class="p-2">
                <ul id="navigationOrder" class="list-group">
                    @foreach($orderedModules as $module)
                        <li class="list-group-item d-flex justify-content-between align-items-center"
                            draggable="true"
                            data-id="{{ $module->id }}">
                            {{ $module->name }}
                            <i class="fas fa-grip-lines"></i>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- SUB MENU --}}
        <div class="col-md-5 offset-md-1">
            <h5 class="fs-6 text-muted">Module Sub Menu Ordering</h5>

            <div class="d-flex align-items-center justify-content-between gap-2 mb-4">
                <select id="moduleSelect" class="form-select form-select-sm">
                    <option value="">-- Select Module --</option>
                    @foreach($orderedModules as $module)
                        <option value="{{ $module->uid }}">{{ $module->name }}</option>
                    @endforeach
                </select>

                <button type="button" id="loadSubMenu" class="btn btn-sm btn-outline-secondary">
                    Display
                </button>
            </div>
            <div class="d-flex align-items-center justify-content-between gap-2">
                <p>Sub menu of {{ $orderedModules->first()->name ?? '' }}</p>
                <button type="button" id="saveSubMenu" class="btn btn-sm btn-outline-primary text-nowrap">
                    Update SubMenu
                </button>
            </div>

            <input type="hidden" name="submenu_order" id="submenuOrderInput">
            <input type="hidden" name="submenu_module_id" id="submenuModuleId">

            <div id="unsavedSubmenu" class="alert alert-warning py-2 px-3 small d-none">
                <i class="fas fa-exclamation-triangle me-1"></i>
                Sub menu order changed. Click Update to save.
            </div>

            <ul id="submenuList" class="list-group mt-2"></ul>
        </div>
    </div>
</form>
@endsection

@push('styles')
<style>
.list-group li {
    cursor: grab;
}
.list-group li.dragging {
    border: 1px solid var(--bs-primary);
    color: var(--bs-primary);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const navigationForm = document.getElementById('navigationForm');
    const moduleSelect = document.getElementById('moduleSelect');

    /**
     * Reusable Drag-and-Drop Function
     * @param {HTMLElement} listEl
     * @param {HTMLElement} warningEl
     * @param {string} dataAttr
     * @returns {Function} getCurrentOrder
     */
    function makeDraggable(listEl, warningEl, dataAttr = 'id') {
        let dragged;
        const initialOrder = Array.from(listEl.children).map(li => li.dataset[dataAttr]).join(',');

        listEl.addEventListener('dragstart', e => {
            dragged = e.target;
            dragged.classList.add('dragging');
        });

        listEl.addEventListener('dragover', e => {
            e.preventDefault();
            const target = e.target.closest('li');
            if (!target || target === dragged) return;
            listEl.insertBefore(dragged, target.nextSibling);
        });

        listEl.addEventListener('dragend', () => {
            dragged.classList.remove('dragging');
            const current = Array.from(listEl.children).map(li => li.dataset[dataAttr]).join(',');
            warningEl.classList.toggle('d-none', current === initialOrder);
        });

        return () => Array.from(listEl.children).map(li => li.dataset[dataAttr]);
    }

    /* ---------------- PRIMARY NAV ---------------- */
    const navList = document.getElementById('navigationOrder');
    const navInput = document.getElementById('navigationOrderInput');
    const navWarning = document.getElementById('unsavedPrimary');
    const getNavOrder = makeDraggable(navList, navWarning, 'id');

    document.getElementById('savePrimaryNav').addEventListener('click', () => {
        navInput.value = JSON.stringify(getNavOrder());
        navigationForm.submit();
    });

    /* ---------------- SUB MENU ---------------- */
    const submenuList = document.getElementById('submenuList');
    const submenuInput = document.getElementById('submenuOrderInput');
    const submenuModuleId = document.getElementById('submenuModuleId');
    const submenuWarning = document.getElementById('unsavedSubmenu');
    let getSubmenuOrder;

    document.getElementById('loadSubMenu').addEventListener('click', function () {
        if (!moduleSelect.value) return;

        fetch(`/navigations/module/${moduleSelect.value}/sub-menu`)
            .then(res => res.json())
            .then(res => {
                submenuList.innerHTML = '';
                submenuModuleId.value = moduleSelect.value;

                res.submenu.forEach(item => {
                    submenuList.insertAdjacentHTML('beforeend', `
                        <li class="list-group-item d-flex justify-content-between"
                            draggable="true"
                            data-route="${item.route}">
                            <span><i class="${item.icon} me-2"></i>${item.label}</span>
                            <i class="fas fa-grip-lines"></i>
                        </li>
                    `);
                });

                getSubmenuOrder = makeDraggable(submenuList, submenuWarning, 'route');
                submenuWarning.classList.add('d-none');
            });
    });

    document.getElementById('saveSubMenu').addEventListener('click', () => {
        if (!getSubmenuOrder) return;

        submenuInput.value = JSON.stringify(getSubmenuOrder());
        navigationForm.submit();
    });

});
</script>
@endpush