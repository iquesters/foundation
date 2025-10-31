<li class="tree-item position-relative {{ $masterData->where('parent_id', $data->id)->count() > 0 ? 'has-children' : '' }}"
    data-id="{{ $data->id }}">
    <div class="tree-item-content d-flex align-items-center text-nowrap">
        @if($masterData->where('parent_id', $data->id)->count() > 0)
            <span class="tree-item-toggle">
                <i class="fas fa-chevron-right"></i>
            </span>
        @endif
        <div class="rounded-2 p-2 {{ $selectedNode && $selectedNode->id == $data->id ? 'bg-light border border-primary' : '' }}">
            <a href="{{ route('master-data.show', $data->id) }}">
                <span class="tree-item-key">{{ $data->key }}</span>
            </a>
            @if($data->value)
                <span class="tree-item-value">- {{ $data->value }}</span>
            @endif
            <span class="ms-2 badge badge-{{ $data->status }}">
                {{ ucfirst($data->status) }}
            </span>

        </div>
    </div>

    @if($masterData->where('parent_id', $data->id)->count() > 0)
        <ul class="tree-item-children">
            @foreach($masterData->where('parent_id', $data->id) as $child)
                @include('foundation::components.inc-with-props.tree-items', [
                    'data' => $child, 
                    'masterData' => $masterData, 
                    'level' => $level + 1,
                    'selectedNode' => $selectedNode ?? null
                ])
            @endforeach
        </ul>
    @endif
</li>