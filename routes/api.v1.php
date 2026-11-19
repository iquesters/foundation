<?php

/*
|--------------------------------------------------------------------------
| Foundation API v1  →  /api/foundation/v1/...
|--------------------------------------------------------------------------
| Loaded by ModuleApi (declared in composer.json extra.platform.api).
| No /api prefix or version here; route names are relative to "api.foundation.v1.".
*/

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Controllers\BusinessEntityDataController;
use Iquesters\Foundation\Http\Controllers\ChatbotDispatchController;
use Iquesters\Foundation\Http\Controllers\DynamicEntityController;
use Iquesters\Foundation\Http\Middleware\ValidateInternalDispatchToken;
use Iquesters\Integration\Models\Integration;
use Iquesters\SmartMessenger\Models\Message;

// Serves smart-messenger: only when smart-messenger and integration are installed.
if (class_exists(Message::class)
    && class_exists(Integration::class)) {
    Route::post('dispatch-queue', [ChatbotDispatchController::class, 'dispatchProcessResponse'])
        ->middleware(ValidateInternalDispatchToken::class)
        ->name('dispatch-queue');
}

// Generic entity CRUD over registered entity tables. Each action checks
// entity.{table}.{view|create|update|delete} (admins always pass).
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('entity')->name('entity.')->group(function () {
        Route::get('list/{entity_name}', [DynamicEntityController::class, 'list'])->name('list');
        Route::get('show/{entity_name}/{data_uid}', [DynamicEntityController::class, 'show'])->name('show');
        Route::post('store/{entity_name}', [DynamicEntityController::class, 'store'])->name('store');
        Route::put('update/{entity_name}/{data_uid}', [DynamicEntityController::class, 'update'])->name('update');
        Route::delete('delete/{entity_name}/{data_uid}', [DynamicEntityController::class, 'delete'])->name('delete');
    });

    // Business entity records: one record across several entity tables ({uid} = the root record's uid).
    // Each action checks entity.{table}.{action} on every part's table.
    Route::prefix('business-entity')->name('business-entity.')->group(function () {
        Route::get('list/{slug}', [BusinessEntityDataController::class, 'list'])->name('list');
        Route::get('show/{slug}/{uid}', [BusinessEntityDataController::class, 'show'])->name('show');
        Route::post('store/{slug}', [BusinessEntityDataController::class, 'store'])->name('store');
        Route::put('update/{slug}/{uid}', [BusinessEntityDataController::class, 'update'])->name('update');
        Route::delete('delete/{slug}/{uid}', [BusinessEntityDataController::class, 'delete'])->name('delete');
    });
});
