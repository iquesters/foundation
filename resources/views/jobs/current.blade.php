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
    \Iquesters\Foundation\Helpers\MetaHelper::make(['Current Queue Jobs', 'Job Monitor'])
)

@section(
    'meta-description',
    \Iquesters\Foundation\Helpers\MetaHelper::description(
        'Real-time monitoring of active queue jobs including processing and pending status across all queues.'
    )
)

@section('content')

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tasks fa-lg text-primary"></i>
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <h6 class="text-muted mb-1 small">Total Jobs</h6>
                        <h4 class="mb-0">{{ number_format($totalStats['total_jobs']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-spinner fa-lg text-success"></i>
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <h6 class="text-muted mb-1 small">Processing</h6>
                        <h4 class="mb-0 text-success">{{ number_format($totalStats['processing']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock fa-lg text-warning"></i>
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <h6 class="text-muted mb-1 small">Pending</h6>
                        <h4 class="mb-0 text-warning">{{ number_format($totalStats['pending']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users fa-lg text-info"></i>
                    </div>
                    <div class="flex-grow-1 ms-2">
                        <h6 class="text-muted mb-1 small">Active Workers</h6>
                        <h4 class="mb-0 text-info">{{ number_format($totalStats['active_workers']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<h5 class="fs-6 text-muted mb-3">Queue Details</h5>

<table id="queuesTable" class="table table-bordered table-hover table-striped">
    <thead>
        <tr>
            <th>Queue Name</th>
            <th>Total Jobs</th>
            <th>Processing</th>
            <th>Pending</th>
            <th>Oldest Job</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($queues as $queue)
            <tr>
                <td>{{ $queue->queue }}</td>
                <td>{{ number_format($queue->total_jobs) }}</td>
                <td class="text-success">{{ number_format($queue->processing_jobs) }}</td>
                <td class="text-warning">{{ number_format($queue->pending_jobs) }}</td>
                <td>
                    @if($queue->oldest_job)
                        <span class="badge badge-draft" title="{{ \Carbon\Carbon::createFromTimestamp($queue->oldest_job)->format('Y-m-d H:i:s') }}">
                            {{ \Carbon\Carbon::createFromTimestamp($queue->oldest_job)->diffForHumans() }}
                        </span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#queuesTable').DataTable({
        responsive: true,
        order: [[1, 'desc']],
        language: {
            emptyTable: "No active jobs in queue"
        }
    });
});
</script>
@endpush