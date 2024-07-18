<?php


use Illuminate\Support\Facades\Route;
use Chredeur\PterodactylApiAddon\Http\Controllers\ServerTransferApplicationController;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;

Route::prefix('/api/application')->middleware(['api', 'throttle:api.application'])->group(function () {

    Route::group(['prefix' => '/servers'], function () {
        /** Transfer Server */
        Route::post('/{server:id}/transfer', [ServerTransferApplicationController::class, 'transfer'])->name('api.application.servers.transfer');
    });

});
