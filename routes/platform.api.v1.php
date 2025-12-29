<?php

use Illuminate\Support\Facades\Route;



/*
| /api/{platform_version}
*/
Route::get('/', function () {
    
    return response()->json([
        'success' => true,
        'message' => 'API enabled and running',
    ]);
});