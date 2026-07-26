<?php

namespace Chredeur\PterodactylApiAddon\Exceptions;

use Illuminate\Http\Response;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Thrown when single sign-on is refused for an account.
 *
 * A 409 rather than a 403 so it cannot be confused with an API key that lacks the right
 * permission: the caller is allowed, the account is simply not eligible.
 */
class SsoNotAvailableException extends DisplayException
{
    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
