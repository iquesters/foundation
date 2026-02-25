@extends('userinterface::layouts.app')

@php
    $tabs = [
        [
            'route' => 'jobs.index',
            'icon' => 'fas fa-fw fa-stream',
            'label' => 'Current',
        ],
        [
            'route' => 'jobs.completed',
            'icon' => 'fas fa-fw fa-check-circle',
            'label' => 'Completed',
        ],
        [
            'route' => 'jobs.failed',
            'icon' => 'fas fa-fw fa-times-circle',
            'label' => 'Failed',
        ],
    ];
@endphp

@section(
    'page-title',
    \Iquesters\Foundation\Helpers\MetaHelper::make(['Failed Jobs', 'Job Monitor'])
)

@section(
    'meta-description',
    \Iquesters\Foundation\Helpers\MetaHelper::description(
        'Monitor and track failed queue jobs from the last 24 hours to identify and resolve processing issues.'
    )
)

@section('content')

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle fa-lg text-danger"></i>
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <h6 class="text-muted mb-1 small">Total Failed</h6>
                        <h4 class="mb-0 text-danger">{{ number_format($totalStats['total_failed']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-layer-group fa-lg text-warning"></i>
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <h6 class="text-muted mb-1 small">Affected Queues</h6>
                        <h4 class="mb-0">{{ number_format($totalStats['unique_queues']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Common Exceptions --}}
@if($commonExceptions->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><i class="fas fa-bug me-2"></i>Most Common Exceptions</h6>
    </div>
    <div class="card-body">
        <div class="list-group list-group-flush">
            @foreach($commonExceptions as $exception)
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-truncate" style="max-width: 80%;">
                        <code class="text-danger">{{ Str::limit($exception->exception_type, 100) }}</code>
                    </span>
                    <x-userinterface::status status="deleted" class="rounded-pill">
                        {{ $exception->count }}
                    </x-userinterface::status>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<h5 class="fs-6 text-muted mb-3">Failed Jobs by Queue (Last 24 Hours)</h5>

<table id="failedTable" class="table table-bordered table-hover table-striped">
    <thead>
        <tr>
            <th>Queue</th>
            <th>Failed Jobs</th>
            <th>Last Failed</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($queues as $queue)
            <tr>
                <td>{{ $queue->queue }}</td>
                <td>{{ number_format($queue->failed_jobs) }}</td>
                <td>
                    <x-userinterface::status status="deleted">
                        {{ \Carbon\Carbon::parse($queue->last_failed_at)->diffForHumans() }}
                    </x-userinterface::status>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

@endsection

@push('scripts')
<script>
$(function () {
    $('#failedTable').DataTable({
        responsive: true,
        order: [[1, 'desc']],
        language: {
            emptyTable: "No failed jobs in the last 24 hours"
        }
    });
});
</script>
@endpush