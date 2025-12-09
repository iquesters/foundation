<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Controllers\ConfigController;
use Iquesters\Foundation\Http\Controllers\EntityController;
use Iquesters\Foundation\Http\Controllers\MasterDataController;
use Iquesters\Foundation\Http\Controllers\ModuleController;
use Iquesters\Foundation\Http\Controllers\OrganisationController;

Route::middleware('web')->group(function () {
    Route::middleware(['auth'])->group(function () {
        Route::prefix('organisations')->name('organisations.')->group(function () {
            Route::get('/', [OrganisationController::class, 'index'])->name('index');
            Route::get('/create', [OrganisationController::class, 'create'])->name('create');
            Route::post('/', [OrganisationController::class, 'store'])->name('store');
            Route::get('{organisationUid}/show', [OrganisationController::class, 'show'])->name('show');
            Route::get('{organisationUid}/edit', [OrganisationController::class, 'edit'])->name('edit');
            Route::put('{organisationUid}', [OrganisationController::class, 'update'])->name('update');
            Route::delete('{organisationUid}', [OrganisationController::class, 'destroy'])->name('destroy');
        });
        
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
        
        Route::resource('master-data', MasterDataController::class)->parameters([
            'master-data' => 'master_datum'
        ]);
    });
});