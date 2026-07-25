<?php

namespace Chredeur\PterodactylApiAddon\Http\Controllers;

use Pterodactyl\Models\Mount;
use Chredeur\PterodactylApiAddon\Transformers\Api\Application\MountTransformer;
use Chredeur\PterodactylApiAddon\Http\Requests\Api\Application\Mounts\GetMountsRequest;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;

/**
 * Exposes the mounts of the panel on the application API.
 *
 * The panel has no such endpoint, mounts are only listed in the admin area. Without it a
 * client has no way to discover mount IDs other than reading them from the admin URLs.
 */
class MountApplicationController extends ApplicationApiController
{
    /**
     * Returns every mount, optionally narrowed down to those usable by a given egg
     * and/or node.
     *
     * Passing both egg_id and node_id gives the mounts that can actually be attached to a
     * server built from that egg on that node, which is the same rule enforced by
     * ServerMountApplicationController.
     */
    public function index(GetMountsRequest $request): array
    {
        $mounts = Mount::query()
            ->when($request->integer('egg_id'), function ($query, $eggId) {
                $query->whereHas('eggs', function ($q) use ($eggId) {
                    $q->where('id', '=', $eggId);
                });
            })
            ->when($request->integer('node_id'), function ($query, $nodeId) {
                $query->whereHas('nodes', function ($q) use ($nodeId) {
                    $q->where('id', '=', $nodeId);
                });
            })
            ->get();

        return $this->fractal->collection($mounts)
            ->transformWith($this->getTransformer(MountTransformer::class))
            ->toArray();
    }

    /**
     * Returns a single mount.
     */
    public function view(GetMountsRequest $request, Mount $mount): array
    {
        return $this->fractal->item($mount)
            ->transformWith($this->getTransformer(MountTransformer::class))
            ->toArray();
    }
}
