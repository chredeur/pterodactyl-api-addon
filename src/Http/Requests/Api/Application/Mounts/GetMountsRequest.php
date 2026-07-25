<?php

namespace Chredeur\PterodactylApiAddon\Http\Requests\Api\Application\Mounts;

use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Requests\Api\Application\ApplicationApiRequest;

/**
 * Read access to the mount listing.
 *
 * Mounts have no dedicated ACL resource in the panel, so this reuses "servers" with read
 * permission, the same resource guarding the server mount endpoints.
 */
class GetMountsRequest extends ApplicationApiRequest
{
    protected ?string $resource = AdminAcl::RESOURCE_SERVERS;

    protected int $permission = AdminAcl::READ;

    public function rules(): array
    {
        return [
            'egg_id' => 'sometimes|integer|exists:eggs,id',
            'node_id' => 'sometimes|integer|exists:nodes,id',
        ];
    }
}
