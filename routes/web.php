<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Controllers\EntityController;

Route::middleware('web')->group(function () {
    Route::middleware(['auth'])->group(function () {
        Route::prefix('entity')->name('entities.')->group(function () {
            Route::get('/', [EntityController::class, 'index'])->name('index');
        });
    });
});