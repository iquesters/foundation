@props([
    'entity', // Required
    'show' => 'auto', // 'auto', 'name', 'date_location', or custom string
    'dateFormat' => 'd-m-Y',
    'customDisplay' => null, // Optional Closure or string
    'extraBadges' => [], // Optional array of ['class' => ..., 'label' => ...]
    'hideStatus' => false, // Optional: to hide the default status badge
])

@php
    $displayContent = $customDisplay
        ? (is_callable($customDisplay) ? $customDisplay($entity) : $customDisplay)
        : match($show) {
            'name' => $entity->name ?? $entity->title ?? 'N/A',
            'date_location' => ($entity->date?->format($dateFormat) ?? 'N/A') . ' ' . ($entity->location()?->name ?? 'N/A'),
            'auto' => property_exists($entity, 'date') 
                ? ($entity->date?->format($dateFormat) ?? 'N/A') . ' ' . ($entity->location()?->name ?? 'N/A')
                : $entity->name ?? $entity->title ?? 'N/A',
            default => $show
        };
@endphp

<div class="d-flex align-items-center justify-content-center gap-1 flex-wrap">
    <div>
        <h5 class="fw-semibold text-muted fs-6 mb-0">
            {{ $displayContent }}
        </h5>
        @if ($show === 'date_location')
            <small class="text-muted">{{ $entity->unit()->name ?? 'N/A' }}</small>
        @endif
    </div>

    @unless($hideStatus)
        <span class="badge badge-{{ strtolower($entity->status) }}">
            {{ ucfirst($entity->status) }}
        </span>
    @endunless

    @foreach ($extraBadges as $badge)
        <span class="badge badge-{{ $badge['class'] }}">
            {{ $badge['label'] }}
        </span>
    @endforeach
</div>
