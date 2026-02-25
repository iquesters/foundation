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
    \Iquesters\Foundation\Helpers\MetaHelper::make(['Completed Jobs', 'Job Monitor'])
)

@section(
    'meta-description',
    \Iquesters\Foundation\Helpers\MetaHelper::description(
        'View successfully completed queue jobs from the last 24 hours with detailed statistics per queue.'
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
                        <i class="fas fa-check-circle fa-lg text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <h6 class="text-muted mb-1 small">Total Completed</h6>
                        <h4 class="mb-0 text-success">{{ number_format($totalStats->total_completed ?? 0) }}</h4>
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
                        <i class="fas fa-tachometer-alt fa-lg text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <h6 class="text-muted mb-1 small">Throughput</h6>
                        <h4 class="mb-0">{{ number_format($totalStats->jobs_per_hour ?? 0, 1) }} <small class="text-muted fs-6">jobs/hour</small></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<h5 class="fs-6 text-muted mb-3">Completed Jobs (Last 24 Hours)</h5>

<table id="completedTable" class="table table-bordered table-hover table-striped">
    <thead>
        <tr>
            <th>Queue</th>
            <th>Completed Jobs</th>
            <th>Last Completed</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($queues as $queue)
            <tr>
                <td>{{ $queue->queue }}</td>
                <td class="text-success">
                    {{ number_format($queue->completed_jobs) }}
                </td>
                <td>
                    <x-userinterface::status status="active">
                        {{ \Carbon\Carbon::parse($queue->last_completed_at)->diffForHumans() }}
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
    $('#completedTable').DataTable({
        responsive: true,
        order: [[1, 'desc']],
        language: {
            emptyTable: "No completed jobs in the last 24 hours"
        }
    });
});
</script>
@endpush