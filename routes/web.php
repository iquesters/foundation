<?php

use Illuminate\Support\Facades\Route;
use Iquesters\Foundation\Http\Controllers\BusinessEntityController;
use Iquesters\Foundation\Http\Controllers\ConfigController;
use Iquesters\Foundation\Http\Controllers\EntityController;
use Iquesters\Foundation\Http\Controllers\MasterDataController;
use Iquesters\Foundation\Http\Controllers\ModuleController;
use Iquesters\Foundation\Http\Controllers\NavigationController;
use Iquesters\Foundation\Http\Controllers\QueueController;
use Iquesters\Foundation\Http\Controllers\JobController;
use Illuminate\Http\Request;
use Iquesters\Foundation\Http\Controllers\QueueManagementController;

Route::middleware('web')->group(function () {
    // -----------------------------
    // Browser timezone POST route
    // -----------------------------
    Route::post('/timezone', function (Request $request) {
        if ($request->timezone) {
            session(['timezone' => $request->timezone]);
        }
        return response()->noContent();
    })->name('timezone.store');
    
    Route::middleware(['auth'])->group(function () {
        
        Route::prefix('entity')->name('entities.')->group(function () {
            Route::get('/', [EntityController::class, 'index'])->name('index');
            Route::get('/create', [EntityController::class, 'create'])->name('create');
            Route::post('/', [EntityController::class, 'store'])->name('store');
            Route::get('/{entityUid}/edit', [EntityController::class, 'edit'])->name('edit');
            Route::put('/entities/{entityUid}', [EntityController::class, 'update'])->name('update');
            Route::delete('/{entityUid}', [EntityController::class, 'destroy'])->name('destroy');
            Route::post('/{entityUid}/publish', [EntityController::class, 'publish'])->name('publish');
            Route::get('/{entityUid}', [EntityController::class, 'show'])->name('show');
        });

        Route::prefix('business-entity')->name('business-entities.')->group(function () {
            Route::get('/', [BusinessEntityController::class, 'index'])->name('index');
            Route::get('/create', [BusinessEntityController::class, 'create'])->name('create');
            Route::get('/entity-options', [BusinessEntityController::class, 'entityOptions'])->name('entity-options');
            Route::post('/', [BusinessEntityController::class, 'store'])->name('store');
            Route::get('/{businessEntityUid}/edit', [BusinessEntityController::class, 'edit'])->name('edit');
            Route::put('/{businessEntityUid}', [BusinessEntityController::class, 'update'])->name('update');
            Route::delete('/{businessEntityUid}', [BusinessEntityController::class, 'destroy'])->name('destroy');
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
            // Route::get('/', function () {
            //     return redirect()->route('ui.list', 'navigations-table');
            // })->name('navigations.index');
            Route::get('/', [NavigationController::class, 'index'])->name('navigations.index');
            Route::get('/details', [NavigationController::class, 'details'])->name('navigation.details');
            Route::get('/module/{moduleUid}/sub-menu', [NavigationController::class, 'loadModuleSubMenu'])->name('navigation.module.submenu');
            Route::post('/save-order', [NavigationController::class, 'saveOrder'])->name('navigation.save-order');
            Route::post('/save-submenu-order', [NavigationController::class, 'saveSubmenuOrder'])->name('navigation.save-submenu-order');
        });     
        
        Route::prefix('jobs')->group(function () {
            Route::get('/', [JobController::class, 'index'])->name('jobs.index');
            Route::get('/completed', [JobController::class, 'completed'])->name('jobs.completed');
            Route::get('/failed', [JobController::class, 'failed'])->name('jobs.failed');
        });

        Route::get('/queue-management', [QueueManagementController::class, 'index'])->name('smart-messenger.queue-management');
    });

    Route::prefix('api/smart-messenger/queue-management')->group(function () {

    Route::get('/scheduler/status',
        [QueueManagementController::class, 'getSchedulerStatus']
    );

    Route::post('/scheduler/start',
        [QueueManagementController::class, 'startScheduler']
    );

    Route::post('/scheduler/stop',
        [QueueManagementController::class, 'stopScheduler']
    );

    Route::get('/queues',
        [QueueManagementController::class, 'getQueues']
    );

    Route::get('/queues/{queueName}/details',
        [QueueManagementController::class, 'getQueueDetails']
    );

    Route::post('/queues/{queueName}/start-workers',
        [QueueManagementController::class, 'startWorkers']
    );

    Route::post('/failed-jobs/{jobId}/retry',
        [QueueManagementController::class, 'retryFailedJob']
    );

    Route::delete('/failed-jobs/{jobId}',
        [QueueManagementController::class, 'deleteFailedJob']
    );
});

});

require __DIR__ . '/api.php';
