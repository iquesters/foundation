@extends('userinterface::layouts.app')

@section('content')
<div>
    <div>
        <h5 class="mb-2 fs-6">{{ $isEdit ? 'Edit' : 'Create' }} Organisation</h5>
    </div>
    <div class="">
        <form action="{{ $actionUrl }}" method="POST" enctype="multipart/form-data">
            @if($isEdit) @method('PUT') @endif
            @csrf
            
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="{{ old('name', $organisation->name ?? '') }}" required>
                @error('name')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $organisation->description ?? '') }}</textarea>
            </div>
            
            <div class="mb-3">
                {{-- <label for="logo" class="form-label">Organisation Logo</label>
                <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                @error('logo')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror --}}
                
                {{-- @if($isEdit && $organisation->logo_url)
                    <div class="mt-2">
                        <img src="{{ $organisation->logo_url }}" alt="Current logo" style="max-height: 100px;" class="img-thumbnail">
                        <p class="small text-muted mt-1">Current logo</p>
                    </div>
                @endif --}}

                {{-- @if($isEdit)
                    <div class="mt-2">
                        @php
                            $logoOptions = (object)[
                                'img' => (object)[
                                    'src' => $organisation->logo_url ?? null,
                                    'alt' => $organisation->name . ' logo',
                                    'title' => 'Current logo',
                                    'width' => '60px',
                                    'class' => 'img-thumbnail',
                                    'container_class' => '',
                                    'aspect_ratio' => '1/1'
                                ],
                                'random_img' => (object)[
                                    'width' => 40,
                                    'height' => 40,
                                    'text' => strtoupper(substr($organisation->name, 0, 2)),
                                    'bg_color' => 'f5b91d',
                                    'text_color' => 'FFFFFF',
                                    'text_font' => 'Roboto',
                                    'img_type' => 'png'
                                ]
                            ];
                        @endphp
                        @include('utils.image', ['options' => $logoOptions])
                        <p class="small text-muted mt-1">Current logo</p>
                    </div>
                @endif --}}
            </div>
            
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="active" {{ old('status', $organisation->status ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ old('status', $organisation->status ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="unknown" {{ old('status', $organisation->status ?? '') === 'unknown' ? 'selected' : '' }}>Unknown</option>
                </select>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('organisations.index') }}" class="btn btn-sm btn-outline-dark">Cancel</a>
                <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection