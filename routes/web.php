<?php


use Illuminate\Support\Facades\Route;
use Chredeur\PterodactylApiAddon\Http\Controllers\ServerTransfertApplicationController;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;

Route::prefix('/api/application')->middleware(['api', 'throttle:api.application'])->group(function () {

    Route::group(['prefix' => '/servers'], function () {
        /** Transfer Server */
        Route::post('/{server:id}/transfer', [ServerTransfertApplicationController::class, 'transfer'])->name('api.application.servers.transfer');
    });

});
