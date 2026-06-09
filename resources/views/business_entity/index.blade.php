@extends('userinterface::layouts.app')

@section('content')
<div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="fs-6 text-muted">Total {{ $businessEntities->count() }} Business Entities</h5>
        <a href="{{ route('business-entities.create') }}" class="btn btn-sm btn-outline-primary">
            <i class="fa-regular fa-fw fa-plus"></i>
            <span class="d-none d-md-inline-block ms-1">Business Entity</span>
        </a>
    </div>

    <div class="table-responsive">
        <table id="business-entities-table" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($businessEntities as $businessEntity)
                    <tr>
                        <td>
                            <a href="{{ route('business-entities.edit', $businessEntity->uid) }}" class="text-decoration-none">
                                {{ $businessEntity->business_entity_name }}
                            </a>
                            <br>
                            <small><small class="text-muted">{{ $businessEntity->uid }}</small></small>
                        </td>
                        <td>
                            <x-userinterface::status :status="$businessEntity->status" />
                        </td>
                        <td>{{ $businessEntity->created_at->format('d M Y') }}</td>
                        <td>
                            <div class="d-flex align-content-center justify-content-center gap-2">
                                <a class="btn btn-sm btn-outline-dark" href="{{ route('business-entities.edit', $businessEntity->uid) }}">
                                    <i class="fas fa-fw fa-edit"></i>
                                </a>

                                <form action="{{ route('business-entities.destroy', $businessEntity->uid) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-fw fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#business-entities-table').DataTable({
            responsive: true,
            order: [[2, 'desc']]
        });
    });
</script>
@endpush
