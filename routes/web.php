<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Controllers\ConfigController;
use Iquesters\Foundation\Http\Controllers\EntityController;
use Iquesters\Foundation\Http\Controllers\ModuleController;

Route::middleware('web')->group(function () {
    Route::middleware(['auth'])->group(function () {
        Route::prefix('entity')->name('entities.')->group(function () {
            Route::get('/', [EntityController::class, 'index'])->name('index');
        });
        
        // Module-Role assignment routes
        Route::get('/modules/assign-to-role', [ModuleController::class, 'assignToRole'])->name('modules.assign-to-role');
        Route::put('/modules/{role}/assign', [ModuleController::class, 'updateRoleModules'])->name('modules.update-role-modules');
        Route::get('/modules/role/{role}', [ModuleController::class, 'getRoleModules'])->name('modules.role-modules');
        
        Route::prefix('modules/config')->group(function () {
            Route::get('/', [ConfigController::class, 'index'])->name('modules.config.index');
            Route::get('/{module?}', [ConfigController::class, 'index'])->name('modules.config.show');
            Route::put('/{module}', [ConfigController::class, 'update'])->name('modules.config.update');
        });
    });
});