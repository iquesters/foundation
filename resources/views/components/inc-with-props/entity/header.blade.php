@props([
    'entity',
    'isCreating' => false,
    'showActions' => false
])

<div class="mb-1">
    <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center justify-content-start gap-2">
            <h5 class="mb-1 fs-6">
                @if($isCreating)
                    Create New Entity
                @else
                    {{ $entity->entity_name }}
                @endif
            </h5>
            @if(!$isCreating)
                <x-userinterface::status :status="$entity->status" />
            @endif
        </div>
        
        @if($showActions && !$isCreating)
            <div class="d-flex align-items-center justify-content-end gap-2 pt-1">
                <a href="{{ route('entities.edit', $entity->uid) }}" 
                   class="btn btn-sm btn-outline-dark d-flex align-items-center justify-content-center gap-2">
                    <i class="fas fa-fw fa-edit"></i> Edit
                </a>
                @if ($entity->status !== 'published')
                    <form action="{{ route('entities.publish', $entity->uid) }}" method="POST" 
                          onsubmit="return confirm('Publishing will lock primary fields permanently.\nDo you want to continue?')">
                        @csrf
                        <button class="btn btn-sm btn-outline-success d-flex align-items-center justify-content-center gap-2">
                            <i class="fas fa-fw fa-upload"></i> Publish
                        </button>
                    </form>
                @elseif ($entity->status !== 'deleted')
                    <form action="{{ route('entities.destroy', $entity->uid) }}" method="POST" 
                          onsubmit="return confirm('Are you sure?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger d-flex align-items-center justify-content-center gap-2">
                            <i class="fas fa-fw fa-trash"></i> Delete
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>
    
    @if($isCreating)
        <code class="text-muted">UID will be auto-generated</code>
    @else
        <code>{{ $entity->uid }}</code>
        <br>
        <small class="mb-0">Slug: {{ $entity->slug }}</small>
        <br>
        <small class="mb-0">Description: {{ $entity->desc }}</small>
    @endif
</div>