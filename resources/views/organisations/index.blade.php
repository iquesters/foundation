@extends('userinterface::layouts.app')

@section('content')
<div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="fs-6 text-muted">Total {{ $organisations->count() }} Organisations</h5>
        @if(auth()->user()->hasRole('super-admin'))
            <a href="{{ route('organisations.create') }}" class="btn btn-sm btn-outline-primary">
               <i class="fa-regular fa-fw fa-plus"></i><span class="d-none d-md-inline-block ms-1">Organisation</span>
            </a>
        @else
            <button class="btn btn-sm btn-outline-primary" onclick="alert('Contact super admin to create organisation.')">
                <i class="fa-regular fa-fw fa-plus"></i><span class="d-none d-md-inline-block ms-1">Organisation</span>
            </button>
        @endif
    </div>
    <div class="">
        <div class="table-responsive">
            <table id="organisations-table" class="table table-striped table-hover">
                <thead>
                    <tr>
                        {{-- <th>UID</th> --}}
                        <th>Name</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($organisations as $organisation)
                    <tr>
                        {{-- <td>{{ $organisation->id }}</td> --}}
                        <td>
                            <a href="{{ route('organisations.show', $organisation->uid) }}" 
                                class="text-decoration-none">
                                {{ $organisation->name }}
                            </a><br>
                            <small><small class="text-muted">{{ $organisation->uid }}</small></small>
                        </td>
                        <td>
                            <span class="badge badge-{{ strtolower($organisation->status) }}">
                                {{ ucfirst($organisation->status) }}
                            </span>
                        </td>
                        <td>{{ $organisation->created_at->format('d M Y') }}</td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    {{-- <li>
                                        <a class="dropdown-item text-info" href="{{ route('organisations.show', $organisation->uid) }}">
                                            <i class="fas fa-fw fa-eye me-1"></i> View
                                        </a>
                                    </li> --}}
                                    <li>
                                        <a class="dropdown-item" href="{{ route('organisations.edit', $organisation->uid) }}">
                                            <i class="fas fa-fw fa-edit me-1"></i> Edit
                                        </a>
                                    </li>
                                    <li>
                                        <form action="{{ route('organisations.destroy', $organisation->uid) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="fas fa-fw fa-trash me-1"></i> Delete
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#organisations-table').DataTable({
            responsive: true,
            order: [[2, 'desc']]
        });
    });
</script>
@endpush