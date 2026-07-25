<?php

namespace Chredeur\PterodactylApiAddon\Exceptions;

use Illuminate\Http\Response;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Thrown when a mount is not allowed for the node and/or the egg of a server.
 *
 * Extends DisplayException to reuse the panel's JSONAPI error rendering, but returns a
 * 409 instead of the default 400: the request is well formed, the resource state is not.
 */
class MountNotEligibleException extends DisplayException
{
    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
