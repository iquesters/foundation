@extends('userinterface::layouts.app')

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make(['Navigation']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('Sidebar items and tabs of each module'))

@php
    $targetText = function ($item) {
        if ($item->url) return $item->url;
        if ($item->table_schema_uid) return 'Table: '.$item->table_schema_uid;
        return $item->route ?: '—';
    };
@endphp

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h5 class="fs-6 mb-1">Navigation</h5>
            <p class="small text-muted mb-0">
                Each module's sidebar items and, under them, the tabs of that page. Drag to reorder.
                Changes you make to a module's item are kept when the module is updated; "Restore" gives it back to the module.
                Fixed items (<i class="fas fa-lock"></i>: the module's Dashboard and each item's "own page" tab) can be moved but not changed.
            </p>
        </div>
        @if ($module)
            <a href="{{ route('navigation-items.create', ['module' => $module->uid]) }}" class="btn btn-sm btn-outline-primary">
                <i class="fa-regular fa-fw fa-plus"></i><span class="ms-1">Sidebar item</span>
            </a>
        @endif
    </div>

    <div class="d-flex flex-nowrap overflow-auto gap-1 mb-3 pb-1" role="tablist" aria-label="Modules">
        @foreach ($modules as $m)
            <a href="{{ route('navigation-items.index', ['module' => $m->uid]) }}"
               class="btn btn-sm text-nowrap {{ $module && $m->id === $module->id ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ \Illuminate\Support\Str::headline($m->name) }}
            </a>
        @endforeach
    </div>

    @if ($items->isEmpty())
        <div class="text-center text-muted py-5">
            <p class="mb-1 fw-medium">No navigation items for this module yet</p>
            <p class="small mb-0">Run <code>php artisan navigation:sync</code> to import the module's menu, or add an item.</p>
        </div>
    @else
        <ul class="list-group" data-nav-sortable>
            @foreach ($items as $item)
                <li class="list-group-item" data-uid="{{ $item->uid }}">
                    @include('foundation::navigation_items.row', ['item' => $item, 'isChild' => false])

                    <ul class="list-group mt-2 ms-4" data-nav-sortable>
                        @foreach ($item->children as $child)
                            <li class="list-group-item py-2" data-uid="{{ $child->uid }}">
                                @include('foundation::navigation_items.row', ['item' => $child, 'isChild' => true])
                            </li>
                        @endforeach
                    </ul>
                    @unless ($item->isDashboard())
                        <a href="{{ route('navigation-items.create', ['module' => $module->uid, 'parent' => $item->uid]) }}"
                           class="btn btn-sm btn-link text-decoration-none ms-4 mt-1 px-0">
                            <i class="fa-regular fa-fw fa-plus"></i> Tab
                        </a>
                    @endunless
                </li>
            @endforeach
        </ul>
    @endif
@endsection

@push('scripts')
<script>
// Drag to reorder within a level (native drag and drop), saved at once.
// Saved on dragend: "drop" doesn't fire when the row is released over itself.
document.querySelectorAll('[data-nav-sortable]').forEach((list) => {
    let dragged = null;
    let before = '';
    const currentOrder = () => [...list.querySelectorAll(':scope > li')].map((x) => x.dataset.uid);
    list.querySelectorAll(':scope > li').forEach((li) => {
        li.draggable = true;
        li.addEventListener('dragstart', (e) => {
            e.stopPropagation();
            dragged = li;
            before = currentOrder().join(',');
            li.classList.add('opacity-50');
        });
        li.addEventListener('dragover', (e) => {
            if (!dragged || dragged.parentElement !== list) return;
            e.preventDefault(); e.stopPropagation();
            if (dragged === li) return;
            const after = e.clientY > li.getBoundingClientRect().top + li.offsetHeight / 2;
            list.insertBefore(dragged, after ? li.nextSibling : li);
        });
        li.addEventListener('dragend', async (e) => {
            if (dragged !== li) return;
            e.stopPropagation();
            li.classList.remove('opacity-50');
            dragged = null;
            const order = currentOrder();
            if (order.join(',') === before) return;
            const response = await fetch(@json(route('navigation-items.reorder')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                credentials: 'same-origin',
                body: JSON.stringify({ order }),
            });
            window.showSnackbar?.(response.ok ? 'Order saved.' : 'The order could not be saved.', response.ok ? 'success' : 'error');
        });
    });
});
</script>
@endpush
