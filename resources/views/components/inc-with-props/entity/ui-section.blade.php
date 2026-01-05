@props([
    'entity' => null,
    'isCreating' => false
])

<div class="accordion-body">
    @if($isCreating)
        <div class="text-muted">
            UI capabilities will be available after the entity is created.
        </div>
    @else
        <div class="d-flex align-items-center justify-content-start gap-2">
            <div>
                @if ($entity->getMeta('table_schema_uid'))
                    <a href="{{ route('ui.list', $entity->getMeta('table_schema_uid')) }}" 
                       class="btn btn-sm btn-outline-info d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-fw fa-list"></i> 
                        List View
                    </a>
                @else
                    <div>✔ List View</div>
                @endif
            </div>

            <div>
                @if ($entity->getMeta('form_schema_uid'))
                    <a href="#" class="btn btn-sm btn-outline-dark d-flex align-items-center justify-content-center gap-2">
                        <i class="fas fa-fw fa-file-alt"></i>
                        Detail View
                    </a>
                @else
                    <div>✔ Detail View</div>
                @endif
            </div>
        </div>
    @endif
</div>