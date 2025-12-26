<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Controllers\ConfigController;
use Iquesters\Foundation\Http\Controllers\EntityController;
use Iquesters\Foundation\Http\Controllers\MasterDataController;
use Iquesters\Foundation\Http\Controllers\ModuleController;
use Iquesters\Foundation\Http\Controllers\NavigationController;

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
        
        Route::resource('master-data', MasterDataController::class)->parameters([
            'master-data' => 'master_datum'
        ]);
        
        Route::prefix('navigations')->group(function () {
            Route::get('/', function () {
                return redirect()->route('ui.list', 'navigations-table');
            })->name('navigations.index');
            // Route::get('/', [NavigationController::class, 'index'])->name('navigations.index');
            Route::get('/details', [NavigationController::class, 'details'])->name('navigation.details');
            Route::get('/module/{moduleUid}/sub-menu', [NavigationController::class, 'loadModuleSubMenu'])->name('navigation.module.submenu');
            Route::post('/save-order', [NavigationController::class, 'saveOrder'])->name('navigation.save-order');
            Route::post('/save-submenu-order', [NavigationController::class, 'saveSubmenuOrder'])->name('navigation.save-submenu-order');
        });
        
    });
});