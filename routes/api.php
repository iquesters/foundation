<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Controllers\ChatbotDispatchController;
use Iquesters\Foundation\Http\Middleware\ValidateInternalDispatchToken;
use Iquesters\Foundation\System\Http\Middleware\RequestMiddleware;
use Iquesters\Foundation\System\Http\Middleware\ResponseMiddleware;

// The dispatch endpoint serves smart-messenger; only register it when
// smart-messenger and integration are installed.
if (! class_exists(\Iquesters\SmartMessenger\Models\Message::class)
    || ! class_exists(\Iquesters\Integration\Models\Integration::class)) {
    return;
}

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