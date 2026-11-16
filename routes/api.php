<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Controllers\ChatbotDispatchController;
use Iquesters\Foundation\Http\Middleware\ValidateInternalDispatchToken;
use Iquesters\Foundation\System\Http\Middleware\RequestMiddleware;
use Iquesters\Foundation\System\Http\Middleware\ResponseMiddleware;

// DEPRECATED alias of POST /api/foundation/v1/dispatch-queue (routes/api.v1.php),
// kept for existing callers; will be removed in a later release.
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