<?php


use Illuminate\Support\Facades\Route;
use Chredeur\PterodactylApiAddon\Http\Controllers\MountApplicationController;
use Chredeur\PterodactylApiAddon\Http\Controllers\ServerMountApplicationController;
use Chredeur\PterodactylApiAddon\Http\Controllers\ServerTransfertApplicationController;
use Chredeur\PterodactylApiAddon\Http\Controllers\SsoLoginController;
use Chredeur\PterodactylApiAddon\Http\Controllers\UserSsoApplicationController;

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

        /** Mounts */
        Route::group(['prefix' => '/mounts'], function () {
            Route::get('/', [MountApplicationController::class, 'index'])
                ->name('api.application.mounts');
            Route::get('/{mount:id}', [MountApplicationController::class, 'view'])
                ->name('api.application.mounts.view');
        });

        /** Single sign-on */
        Route::post('/users/{user:id}/sso', [UserSsoApplicationController::class, 'store'])
            ->name('api.application.users.sso');

    });

/*
| The redemption side of single sign-on. On the web group because it needs the session,
| and without "guest" so a visitor already signed in as another account can be switched
| over rather than bounced.
|
| Under /auth/ on purpose: RequireTwoFactorAuthentication lets that prefix through, which
| it must, otherwise the redirect could never complete. The policy still applies on the
| page the visitor lands on.
|
| Throttled with the panel's own authentication limiter. The token is 32 random bytes so
| guessing is not a concern, but there is no reason to leave the endpoint unmetered.
*/
Route::middleware(['web', 'throttle:authentication'])
    ->get('/auth/sso/{token}', SsoLoginController::class)
    ->name('auth.sso');
