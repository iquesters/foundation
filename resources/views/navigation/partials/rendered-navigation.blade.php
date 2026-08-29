@php
    $sections = $sections ?? ['sidebar', 'minibar', 'tabs'];
    $navigationGroups = $navigationGroups ?? [];
@endphp

<div class="navigation-rendered">
    @foreach($navigationGroups as $navigationName => $groups)
        <div class="navigation-rendered__section mb-4">
            <h6 class="text-uppercase text-muted small mb-2">{{ $navigationName }}</h6>
            @foreach($sections as $section)
                @if(!empty($groups[$section]))
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="small">{{ $section }}</strong>
                            <span class="badge bg-light text-dark">{{ count($groups[$section]) }}</span>
                        </div>
                        <div class="list-group">
                            @foreach($groups[$section] as $item)
                                <div class="list-group-item d-flex align-items-center justify-content-between">
                                    <span>
                                        <i class="{{ $item['icon'] ?? 'fas fa-circle' }} me-2"></i>
                                        {{ $item['label'] ?? $item['id'] }}
                                    </span>
                                    <small class="text-muted">{{ $item['route'] ?? $item['id'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endforeach
</div>
