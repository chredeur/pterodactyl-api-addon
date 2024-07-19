<?php


use Illuminate\Support\Facades\Route;
use Chredeur\PterodactylApiAddon\Http\Controllers\ServerTransfertApplicationController;

Route::prefix('/api/application')->middleware(['api', 'throttle:api.application'])->group(function () {

    Route::group(['prefix' => '/servers'], function () {
        /** Transfer Server */
        Route::post('/{server:id}/transfer', [ServerTransfertApplicationController::class, 'transfer']);
    });

});
