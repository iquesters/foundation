@extends('userinterface::layouts.app')

@section('content')
<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center">
            @include('organisation::components.entity-name-status', [
                'entity' => $organisation
            ])
        </div>
        <div>
            <a href="{{ route('organisations.edit', $organisation->uid) }}" class="btn btn-sm btn-outline-dark">
                <i class="fas fa-fw fa-edit"></i><span class="d-none d-md-inline-block ms-2">Edit</span>
            </a>
        </div>
    </div>
    <div class="row">
        <div class="col-md-9 d-flex flex-column align-items-start justify-content-center text-muted">
            <p><strong>Name:</strong> {{ $organisation->name }}</p>
            <p><strong>Description:</strong> {{ $organisation->description ?? 'N/A' }}</p>
        </div>
    </div>
</div>
@endsection