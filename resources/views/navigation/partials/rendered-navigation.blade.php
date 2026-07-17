@php
    $sections = $sections ?? ['sidebar', 'minibar', 'tabs'];
    $moduleGroups = $moduleGroups ?? [];
    $foundationGroups = $foundationGroups ?? [];
@endphp

<div class="navigation-rendered">
    <div class="navigation-rendered__section mb-4">
        <h6 class="text-uppercase text-muted small mb-2">Module Sidebar</h6>
        @foreach($sections as $section)
            @if(!empty($moduleGroups[$section]))
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">{{ ucfirst($section) }}</strong>
                        <span class="badge bg-light text-dark">{{ count($moduleGroups[$section]) }}</span>
                    </div>
                    <div class="list-group">
                        @foreach($moduleGroups[$section] as $item)
                            <div class="list-group-item d-flex align-items-center justify-content-between">
                                <span>
                                    <i class="{{ $item['icon'] ?? 'fas fa-circle' }} me-2"></i>
                                    {{ $item['label'] }}
                                </span>
                                <small class="text-muted">{{ $item['route'] ?? $item['id'] }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <div class="navigation-rendered__section mb-4">
        <h6 class="text-uppercase text-muted small mb-2">Foundation Sidebar</h6>
        @foreach($sections as $section)
            @if(!empty($foundationGroups[$section]))
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small">{{ ucfirst($section) }}</strong>
                        <span class="badge bg-light text-dark">{{ count($foundationGroups[$section]) }}</span>
                    </div>
                    <div class="list-group">
                        @foreach($foundationGroups[$section] as $item)
                            <div class="list-group-item d-flex align-items-center justify-content-between">
                                <span>
                                    <i class="{{ $item['icon'] ?? 'fas fa-circle' }} me-2"></i>
                                    {{ $item['label'] }}
                                </span>
                                <small class="text-muted">{{ $item['route'] ?? $item['id'] }}</small>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>
