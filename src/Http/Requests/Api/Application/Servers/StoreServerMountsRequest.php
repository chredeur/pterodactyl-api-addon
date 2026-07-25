<?php

namespace Chredeur\PterodactylApiAddon\Http\Requests\Api\Application\Servers;

use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Http\Requests\Api\Application\ApplicationApiRequest;

/**
 * Validates a bulk mount attach request.
 *
 * Same ACL as ServerWriteRequest: the "servers" resource with write permission.
 */
class StoreServerMountsRequest extends ApplicationApiRequest
{
    protected ?string $resource = AdminAcl::RESOURCE_SERVERS;

    protected int $permission = AdminAcl::WRITE;

    public function rules(): array
    {
        return [
            'mounts' => 'required|array|min:1',
            // "exists" returns a clear 422 for an unknown mount rather than a
            // misleading 409 from the eligibility check.
            'mounts.*' => 'required|integer|distinct|exists:mounts,id',
        ];
    }

    public function attributes(): array
    {
        return [
            'mounts' => 'Mount ID list',
            'mounts.*' => 'Mount ID',
        ];
    }

    /**
     * Returns the requested mount IDs as integers.
     *
     * @return int[]
     */
    public function mountIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('mounts', []))));
    }
}
