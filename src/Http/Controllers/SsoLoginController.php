<?php

namespace Chredeur\PterodactylApiAddon\Http\Controllers;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Facades\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Chredeur\PterodactylApiAddon\Services\SsoTokenService;
use Pterodactyl\Http\Controllers\Controller;

/**
 * Consumes a single sign-on token and opens a session.
 *
 * Registered on the web middleware group, since it needs the session, and deliberately
 * not behind "guest": someone already signed in as another account must be able to land
 * here and be switched over.
 */
class SsoLoginController extends Controller
{
    public function __construct(private SsoTokenService $tokens)
    {
    }

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $payload = $this->tokens->consume($token);

        if (is_null($payload)) {
            return $this->reject('token is unknown, expired or already used');
        }

        $user = User::query()->find($payload['user_id']);

        // Checked again on redemption, not only at issuance: the account may have gained
        // two-factor authentication or administrator rights in between, and the token
        // would otherwise still be honoured.
        if (is_null($user) || $user->use_totp || $user->root_admin) {
            return $this->reject('account is no longer eligible for single sign-on');
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::guard()->loginUsingId($user->id);

        // The account owner must be able to see that a session was opened without a
        // password. Shows up under the Activity tab of the account.
        Activity::event('auth:sso')->withRequestMetadata()->subject($user)->log();

        return redirect($payload['redirect'] ?? '/');
    }

    /**
     * Falls back to the login page. The reason goes to the logs rather than the screen:
     * telling a visitor why a token was refused only helps someone probing the endpoint.
     */
    protected function reject(string $reason): RedirectResponse
    {
        Log::info('[pterodactyl-api-addon] SSO login refused: ' . $reason);

        return redirect('/auth/login');
    }
}
