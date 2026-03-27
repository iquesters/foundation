<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Controllers\ChatbotDispatchController;
use Iquesters\Foundation\Http\Middleware\ValidateInternalDispatchToken;
use Iquesters\Foundation\System\Http\Middleware\RequestMiddleware;
use Iquesters\Foundation\System\Http\Middleware\ResponseMiddleware;

Route::prefix('api')
    ->middleware([
        'api',
        RequestMiddleware::class,
        ResponseMiddleware::class,
        ValidateInternalDispatchToken::class,
    ])
    ->group(function () {
        Route::post('/dispatch-queue', [
            ChatbotDispatchController::class,
            'dispatchProcessResponse',
        ]);
    });