<?php

namespace Chredeur\PterodactylApiAddon\Transformers\Api\Application;

use Pterodactyl\Models\Mount;
use Pterodactyl\Transformers\Api\Application\BaseTransformer;

/**
 * The panel ships no transformer for Mount, so this one belongs to the addon. The
 * exposed fields mirror the Pterodactyl\Models\Mount model.
 */
class MountTransformer extends BaseTransformer
{
    public function getResourceName(): string
    {
        return Mount::RESOURCE_NAME;
    }

    public function transform(Mount $model): array
    {
        return [
            'id' => $model->id,
            'uuid' => $model->uuid,
            'name' => $model->name,
            'description' => $model->description,
            'source' => $model->source,
            'target' => $model->target,
            'read_only' => $model->read_only,
            'user_mountable' => $model->user_mountable,
        ];
    }
}
