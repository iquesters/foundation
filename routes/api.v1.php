<?php

/*
|--------------------------------------------------------------------------
| Foundation API v1  →  /api/foundation/v1/...
|--------------------------------------------------------------------------
| Loaded by ModuleApi (declared in composer.json extra.platform.api).
| No /api prefix or version here; route names are relative to "api.foundation.v1.".
*/

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Controllers\ChatbotDispatchController;
use Iquesters\Foundation\Http\Middleware\ValidateInternalDispatchToken;

// Serves smart-messenger: only when smart-messenger and integration are installed.
if (class_exists(\Iquesters\SmartMessenger\Models\Message::class)
    && class_exists(\Iquesters\Integration\Models\Integration::class)) {
    Route::post('dispatch-queue', [ChatbotDispatchController::class, 'dispatchProcessResponse'])
        ->middleware(ValidateInternalDispatchToken::class)
        ->name('dispatch-queue');
}
