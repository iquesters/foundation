@props([
    'sectionId',
    'title',
    'collapseId',
    'colorClass' => 'primary',
    'showByDefault' => true
])

<div id="{{ $sectionId }}" class="p-2 text-{{ $colorClass }}">
    {{ $title }}
</div>

<div class="accordion-item">
    <h2 class="accordion-header">
        <button class="accordion-button bg-{{ $colorClass }}-subtle text-{{ $colorClass }}"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId }}">
            {{ $slot->isEmpty() ? $title : '' }}
            @if(!$slot->isEmpty())
                {{ $attributes->get('headerContent') ?? $title }}
            @endif
        </button>
    </h2>
    <div id="{{ $collapseId }}" class="accordion-collapse collapse {{ $showByDefault ? 'show' : '' }}">
        <div class="accordion-body {{ $attributes->get('bodyClass', 'p-0') }}">
            {{ $slot }}
        </div>
    </div>
</div>