{{-- One navigation item in the editor list. --}}
<div class="d-flex flex-wrap align-items-center gap-2">
    <i class="fas fa-grip-vertical text-muted" title="Drag to reorder" style="cursor: grab;"></i>
    @if ($item->displayIcon())
        <i class="{{ $item->displayIcon() }} fa-fw"></i>
    @endif
    <span class="fw-medium {{ $item->status !== 'active' ? 'text-decoration-line-through text-muted' : '' }}">{{ $item->displayLabel() }}</span>
    @if ($isChild)
        <span class="badge text-bg-light border">tab</span>
    @endif
    @if ($item->isDashboard())
        <span class="badge text-bg-primary" title="Every module has one. It can be moved, not changed.">module dashboard</span>
    @elseif ($item->isSelfTab())
        <span class="badge text-bg-primary" title="The sidebar item's own page, opened from the sidebar wherever this tab is. It can be moved, not changed.">own page</span>
    @endif
    @if ($item->status !== 'active')
        <span class="badge text-bg-secondary">hidden</span>
    @endif
    @if ($item->source === 'admin')
        <span class="badge text-bg-info">added here</span>
    @elseif ($item->customized)
        <span class="badge text-bg-warning" title="Module updates keep your change">changed</span>
    @endif
    @if ($item->permission)
        <span class="badge text-bg-light border" title="Only users with this permission see it"><i class="fas fa-lock fa-fw"></i> {{ $item->permission }}</span>
    @endif
    <span class="small text-muted text-truncate" style="max-width: 18rem;">{{ $item->isSelfTab() ? 'Same page as the sidebar item' : $targetText($item) }}</span>

    @if ($item->isLocked())
        <span class="ms-auto small text-muted" title="Fixed item: drag to move it"><i class="fas fa-fw fa-lock"></i></span>
    @else
    <div class="ms-auto d-flex gap-1">
        <a href="{{ route('navigation-items.edit', $item->uid) }}" class="btn btn-sm btn-outline-dark" title="Edit"><i class="fas fa-fw fa-edit"></i></a>
        @if ($item->source === 'module' && $item->customized)
            <form method="POST" action="{{ route('navigation-items.restore', $item->uid) }}">
                @csrf
                <button class="btn btn-sm btn-outline-secondary" title="Restore the module's version"><i class="fas fa-fw fa-rotate-left"></i></button>
            </form>
        @endif
        <form method="POST" action="{{ route('navigation-items.destroy', $item->uid) }}"
              onsubmit="return confirm(@js($item->source === 'admin' ? 'Delete "'.$item->label.'"?' : 'Hide "'.$item->label.'"? You can show it again later.'))">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" title="{{ $item->source === 'admin' ? 'Delete' : 'Hide' }}"><i class="fas fa-fw {{ $item->source === 'admin' ? 'fa-trash' : 'fa-eye-slash' }}"></i></button>
        </form>
    </div>
@endif
</div>
