@extends('userinterface::layouts.app')

@php $moduleTitle = \Illuminate\Support\Str::headline($module->name); @endphp

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make([$moduleTitle]))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description($module->description ?: $moduleTitle))

@section('content')
    <div class="d-flex align-items-center gap-2 mb-1">
        @if ($module->getMeta('module_icon'))
            <i class="{{ $module->getMeta('module_icon') }} fa-fw text-primary"></i>
        @endif
        <h5 class="fs-6 mb-0">{{ $moduleTitle }}</h5>
    </div>
    @if ($module->description)
        <p class="small text-muted mb-3">{{ $module->description }}</p>
    @endif

    @if ($items->isEmpty())
        <div class="text-center text-muted py-5">
            <p class="mb-0 fw-medium">This module has no pages you can open yet.</p>
        </div>
    @else
        <div class="row g-2 g-md-3">
            @foreach ($items as $item)
                @php $href = $item->href(); @endphp
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <a href="{{ $href }}" class="card h-100 text-decoration-none text-body {{ $href === '#' ? 'pe-none opacity-50' : '' }}">
                        <div class="card-body d-flex align-items-center gap-3 py-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width: 2.5rem; height: 2.5rem;">
                                <i class="{{ $item->icon ?: 'fas fa-circle' }}" aria-hidden="true"></i>
                            </span>
                            <span style="min-width: 0;">
                                <span class="d-block fw-medium text-truncate">{{ $item->label }}</span>
                                @php $tabCount = $item->children->reject->isSelfTab()->count(); @endphp
                                @if ($tabCount > 0)
                                    <span class="d-block small text-muted">{{ $tabCount }} {{ \Illuminate\Support\Str::plural('tab', $tabCount) }}</span>
                                @endif
                            </span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
@endsection
