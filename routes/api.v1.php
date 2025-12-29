<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Foundation API Health / Root
|--------------------------------------------------------------------------
| GET /api/{platform_version}
*/

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'API enabled and running for foundation',
        'platform_version' => request()->route('platform_version'),
    ]);
});

Route::get('/hola', function () {
    return response()->json([
        'success' => true,
        'message' => 'hello, hola',
        'platform_version' => request()->route('platform_version'),
    ]);
});