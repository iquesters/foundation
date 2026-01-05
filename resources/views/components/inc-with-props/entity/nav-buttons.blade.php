@props(['buttons'])

<div class="d-flex flex-wrap gap-2">
    @foreach($buttons as $btn)
        <button
            type="button"
            class="btn btn-sm btn-outline-{{ $btn['btn'] }}"
            onclick="openSection('{{ $btn['header'] }}','{{ $btn['collapse'] }}')"
            data-bs-toggle="tooltip"
            data-bs-placement="top"
            title="{{ $btn['tooltip'] }}"
        >
            {{ $btn['label'] }}
        </button>
    @endforeach
</div>