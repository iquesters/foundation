@php
    $layout = class_exists(\Iquesters\UserInterface\UserInterfaceServiceProvider::class)
        ? 'userinterface::layouts.app'
        : config('product.layout');
@endphp

@extends($layout)

@section('content')
hola
@endsection