<?php

namespace Chredeur\PterodactylApiAddon\Http\Requests\Api\Application\Users;

use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Requests\Api\Application\ApplicationApiRequest;

/**
 * Validates a single sign-on token request.
 *
 * Write permission on users is required: the call does not modify the account, but it
 * hands out the ability to open a session as that account, which is not a read.
 */
class CreateSsoTokenRequest extends ApplicationApiRequest
{
    protected ?string $resource = AdminAcl::RESOURCE_USERS;

    protected int $permission = AdminAcl::WRITE;

    public function rules(): array
    {
        return [
            // Must be a path on this panel. Leading "//" is rejected because a browser
            // reads it as a protocol relative URL, which would turn this into an open
            // redirect pointing at any host.
            'redirect' => ['sometimes', 'string', 'max:255', 'regex:/^\/(?!\/)[A-Za-z0-9\-._~\/]*$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'redirect' => 'Redirect path',
        ];
    }
}
