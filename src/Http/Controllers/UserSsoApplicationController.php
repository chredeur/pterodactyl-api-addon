<?php

namespace Chredeur\PterodactylApiAddon\Http\Controllers;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Chredeur\PterodactylApiAddon\Services\SsoTokenService;
use Chredeur\PterodactylApiAddon\Exceptions\SsoNotAvailableException;
use Chredeur\PterodactylApiAddon\Http\Requests\Api\Application\Users\CreateSsoTokenRequest;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;

/**
 * Hands out single use sign-on links for a user.
 *
 * Intended for a billing panel that already provisions servers with this API key and
 * wants to send a customer straight into their server without a password.
 */
class UserSsoApplicationController extends ApplicationApiController
{
    public function __construct(private SsoTokenService $tokens)
    {
        parent::__construct();
    }

    /**
     * Returns a URL that signs the given user in and drops them on the requested page.
     *
     * @throws SsoNotAvailableException
     */
    public function store(CreateSsoTokenRequest $request, User $user): JsonResponse
    {
        $this->assertAccountIsEligible($user);

        $token = $this->tokens->issue($user, $request->input('redirect'));

        return new JsonResponse([
            'object' => 'sso',
            'attributes' => [
                'url' => url('/auth/sso/' . $token),
                'expires_at' => CarbonImmutable::now()->addSeconds(SsoTokenService::TTL_SECONDS)->toAtomString(),
            ],
        ]);
    }

    /**
     * Two accounts never get an SSO link.
     *
     * Accounts with two-factor authentication: opening a session directly never reaches
     * the TOTP challenge, which lives in the password login flow. Issuing a link would
     * silently defeat a protection the account owner turned on, so these accounts are
     * sent through the normal login page instead.
     *
     * Administrators: an application key already grants wide control over the panel, but
     * it does not grant an interactive administrator session. Refusing here keeps a
     * leaked key away from that.
     *
     * @throws SsoNotAvailableException
     */
    protected function assertAccountIsEligible(User $user): void
    {
        if ($user->use_totp) {
            throw new SsoNotAvailableException(
                'This account has two-factor authentication enabled and must be signed in through the login page.'
            );
        }

        if ($user->root_admin) {
            throw new SsoNotAvailableException(
                'Single sign-on is not available for administrator accounts.'
            );
        }
    }
}
