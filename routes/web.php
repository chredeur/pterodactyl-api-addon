<?php


use Illuminate\Support\Facades\Route;
use Chredeur\PterodactylApiAddon\Http\Controllers\ServerMountApplicationController;
use Chredeur\PterodactylApiAddon\Http\Controllers\ServerTransfertApplicationController;

/*
| Same middleware stack as the panel's application API. The "application-api" group is
| required: it provides model binding and checks the key belongs to an administrator.
|
| No ->scopeBindings() here, unlike the panel: scoping would resolve {mount:id} through
| the Server::mounts() relation and return a 404 for any mount not attached yet.
*/
Route::prefix('/api/application')
    ->middleware(['api', 'application-api', 'throttle:api.application'])
    ->group(function () {

        Route::group(['prefix' => '/servers'], function () {
            /** Transfer Server */
            Route::post('/transfer', [ServerTransfertApplicationController::class, 'transfer'])->name('api.application.servers.transfer');

            /** Server Mounts */
            Route::group(['prefix' => '/{server:id}/mounts'], function () {
                Route::get('/', [ServerMountApplicationController::class, 'index'])
                    ->name('api.application.servers.mounts');

                Route::post('/', [ServerMountApplicationController::class, 'storeBulk'])
                    ->name('api.application.servers.mounts.store');
                Route::post('/{mount:id}', [ServerMountApplicationController::class, 'store'])
                    ->name('api.application.servers.mounts.attach');

                Route::delete('/{mount:id}', [ServerMountApplicationController::class, 'delete'])
                    ->name('api.application.servers.mounts.delete');
            });
        });

    });
