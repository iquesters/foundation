@props([
    'entity' => null,
    'isCreating' => false
])

@if($isCreating)
    <div class="alert alert-light text-muted mb-0">
        <i class="fas fa-info-circle me-1"></i>
        User interface views will be available after the entity is created.
    </div>
@else
    <div class="row g-3 p-2">

        {{-- LIST VIEW --}}
        <div class="col-md-6">
            <div class="p-3 bg-success-subtle rounded">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-list text-success"></i>
                        <h6 class="mb-0">List View</h6>
                    </div>

                    @if ($entity->getMeta('table_schema_uid'))
                        <a href="{{ route('ui.list', $entity->getMeta('table_schema_uid')) }}"
                           class="btn btn-sm btn-outline-success"
                           title="View List UI">
                            <i class="fas fa-eye"></i>
                        </a>
                    @else
                        <span class="badge badge-draft">N/A</span>
                    @endif
                </div>

                <small class="text-muted">
                    Auto-generated from the table schema configuration.
                </small>
            </div>
        </div>

        {{-- DETAIL / FORM VIEW --}}
        <div class="col-md-6">
            <div class="p-3 bg-info-subtle rounded">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-file-alt text-info"></i>
                        <h6 class="mb-0">Detail / Form View</h6>
                    </div>

                    @if ($entity->getMeta('form_schema_uid'))
                        <a href="#"
                           class="btn btn-sm btn-outline-info"
                           title="View Form UI">
                            <i class="fas fa-eye"></i>
                        </a>
                    @else
                        <span class="badge badge-draft">N/A</span>
                    @endif
                </div>

                <small class="text-muted">
                    Auto-generated from the form schema configuration.
                </small>
            </div>
        </div>

    </div>
@endif
