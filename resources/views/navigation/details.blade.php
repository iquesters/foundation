@extends('userinterface::layouts.app')

@php
    $sections = $editorPayload['sections'] ?? ['sidebar', 'minibar', 'tabs'];
    $navigationGroups = $editorPayload ?? [];
@endphp

@section('content')
<form method="POST" id="navigationForm" action="{{ route('navigation.save-order') }}">
    @csrf

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-1">Module Navigation</h5>
                    <p class="text-muted small mb-3">Rendered from the saved navigation JSON and grouped by placement.</p>
                    <div class="nav-editor" data-navigation-editor="module_navigation">
                        @foreach($sections as $section)
                            <div class="nav-editor-section mb-4" data-section="{{ $section }}">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0 text-uppercase small text-muted">{{ $section }}</h6>
                                    <span class="badge bg-light text-dark">{{ count($moduleNavigationGroups[$section] ?? []) }}</span>
                                </div>
                                <ul class="list-group navigation-list" data-list="{{ $section }}">
                                    @forelse($moduleNavigationGroups[$section] ?? [] as $item)
                                        <li class="list-group-item d-flex justify-content-between align-items-center"
                                            draggable="true"
                                            data-id="{{ $item['id'] }}"
                                            data-label="{{ $item['label'] }}"
                                            data-icon="{{ $item['icon'] ?? '' }}"
                                            data-placement="{{ $item['placement'] ?? $section }}"
                                            data-module-uid="{{ $item['module_uid'] ?? '' }}"
                                            data-target-module-uid="{{ $item['target_module_uid'] ?? '' }}">
                                            <span>
                                                <i class="{{ $item['icon'] ?? 'fas fa-circle' }} me-2"></i>
                                                {{ $item['label'] }}
                                            </span>
                                            <small class="text-muted">{{ $item['placement'] ?? $section }}</small>
                                        </li>
                                    @empty
                                        <li class="list-group-item text-muted small" data-empty="true">No items in this section.</li>
                                    @endforelse
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-1">Foundation Navigation</h5>
                    <p class="text-muted small mb-3">Foundation items are also driven by the same JSON model.</p>
                    <div class="nav-editor" data-navigation-editor="foundation_navigation">
                        @foreach($sections as $section)
                            <div class="nav-editor-section mb-4" data-section="{{ $section }}">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0 text-uppercase small text-muted">{{ $section }}</h6>
                                    <span class="badge bg-light text-dark">{{ count($foundationNavigationGroups[$section] ?? []) }}</span>
                                </div>
                                <ul class="list-group navigation-list" data-list="{{ $section }}">
                                    @forelse($foundationNavigationGroups[$section] ?? [] as $item)
                                        <li class="list-group-item d-flex justify-content-between align-items-center"
                                            draggable="true"
                                            data-id="{{ $item['id'] }}"
                                            data-label="{{ $item['label'] }}"
                                            data-icon="{{ $item['icon'] ?? '' }}"
                                            data-placement="{{ $item['placement'] ?? $section }}"
                                            data-module-uid="{{ $item['module_uid'] ?? '' }}"
                                            data-target-module-uid="{{ $item['target_module_uid'] ?? '' }}">
                                            <span>
                                                <i class="{{ $item['icon'] ?? 'fas fa-circle' }} me-2"></i>
                                                {{ $item['label'] }}
                                            </span>
                                            <small class="text-muted">{{ $item['placement'] ?? $section }}</small>
                                        </li>
                                    @empty
                                        <li class="list-group-item text-muted small" data-empty="true">No items in this section.</li>
                                    @endforelse
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-1">Saved Payload</h5>
                    <p class="text-muted small mb-3">This is the JSON that will be posted back to the server.</p>
                    <pre class="bg-light rounded p-3 small mb-0" id="navigationPreview" style="min-height: 420px; white-space: pre-wrap;"></pre>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-1">Rendered Navigation</h5>
                    <p class="text-muted small mb-3">This preview uses the same JSON payload that will drive the live sidebar, minibar, and tabs.</p>
                    @include('foundation::navigation.partials.rendered-navigation', [
                        'sections' => $sections,
                        'navigationGroups' => $navigationGroups,
                    ])
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="module_navigation" id="moduleNavigationInput">
    <input type="hidden" name="foundation_navigation" id="foundationNavigationInput">

    <div class="mt-4 d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary">Save Navigation</button>
    </div>
</form>
@endsection

@push('styles')
<style>
.navigation-list li {
    cursor: grab;
}

.navigation-list li.dragging {
    opacity: 0.6;
    border-color: var(--bs-primary);
}

.nav-editor-section:not(:last-child) {
    border-bottom: 1px dashed rgba(0, 0, 0, 0.08);
    padding-bottom: 1rem;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sections = @json($sections);

    function makeDraggable(listEl) {
        if (!listEl) {
            return;
        }

        let dragged = null;

        listEl.addEventListener('dragstart', function (e) {
            dragged = e.target.closest('li[data-id]');
            if (dragged) {
                dragged.classList.add('dragging');
            }
        });

        listEl.addEventListener('dragover', function (e) {
            e.preventDefault();
            const target = e.target.closest('li[data-id]');
            if (!target || !dragged || target === dragged) {
                return;
            }

            listEl.insertBefore(dragged, target.nextSibling);
        });

        listEl.addEventListener('dragend', function () {
            if (dragged) {
                dragged.classList.remove('dragging');
            }
            dragged = null;
            syncPayload();
        });
    }

    function readSection(listEl, section) {
        return Array.from(listEl.querySelectorAll('li[data-id]')).map((li, index) => ({
            id: li.dataset.id,
            label: li.dataset.label,
            icon: li.dataset.icon || null,
            placement: section,
            module_uid: li.dataset.moduleUid || null,
            target_module_uid: li.dataset.targetModuleUid || null,
            sort_order: (index + 1) * 10,
            visible: true,
            enabled: true,
            locked: false,
        }));
    }

    function readNavigation(editorName) {
        const editor = document.querySelector('[data-navigation-editor="' + editorName + '"]');
        const payload = [];

        sections.forEach(function (section) {
            const list = editor ? editor.querySelector('[data-list="' + section + '"]') : null;
            if (!list) {
                return;
            }
            payload.push(...readSection(list, section));
        });

        return payload;
    }

    function syncPayload() {
        const modulePayload = readNavigation('module_navigation');
        const foundationPayload = readNavigation('foundation_navigation');

        document.getElementById('moduleNavigationInput').value = JSON.stringify(modulePayload);
        document.getElementById('foundationNavigationInput').value = JSON.stringify(foundationPayload);
        document.getElementById('navigationPreview').textContent = JSON.stringify({
            module_navigation: modulePayload,
            foundation_navigation: foundationPayload,
        }, null, 2);
    }

    document.querySelectorAll('.navigation-list').forEach(makeDraggable);
    syncPayload();

    document.getElementById('navigationForm').addEventListener('submit', function () {
        syncPayload();
    });
});
</script>
@endpush
