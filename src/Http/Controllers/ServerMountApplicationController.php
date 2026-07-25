<?php

namespace Chredeur\PterodactylApiAddon\Http\Controllers;

use Illuminate\Http\Response;
use Pterodactyl\Models\Mount;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\MountServer;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Chredeur\PterodactylApiAddon\Exceptions\MountNotEligibleException;
use Chredeur\PterodactylApiAddon\Transformers\Api\Application\MountTransformer;
use Chredeur\PterodactylApiAddon\Http\Requests\Api\Application\Servers\StoreServerMountsRequest;
use Pterodactyl\Http\Requests\Api\Application\Servers\GetServerRequest;
use Pterodactyl\Http\Requests\Api\Application\Servers\ServerWriteRequest;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Throwable;

/**
 * Attaches and detaches mounts on a server from the application API.
 *
 * The panel only exposes this through the admin area, which is session and CSRF
 * protected. This mirrors Admin\ServersController::addMount()/deleteMount() and adds
 * the eligibility check plus the Wings sync.
 */
class ServerMountApplicationController extends ApplicationApiController
{
    /**
     * ServerMountApplicationController constructor.
     */
    public function __construct(
        private ConnectionInterface    $connection,
        private DaemonServerRepository $daemonServerRepository
    ) {
        parent::__construct();
    }

    /**
     * Returns the mounts currently attached to a server.
     */
    public function index(GetServerRequest $request, Server $server): array
    {
        return $this->fractal->collection($server->mounts)
            ->transformWith($this->getTransformer(MountTransformer::class))
            ->toArray();
    }

    /**
     * Attaches a single mount to a server.
     *
     * @throws Throwable
     */
    public function store(ServerWriteRequest $request, Server $server, Mount $mount): array
    {
        $this->assertMountsAreEligible($server, [$mount->id]);

        $this->attachMounts($server, [$mount->id]);
        $this->syncWithDaemon($server);

        return $this->fractal->item($mount)
            ->transformWith($this->getTransformer(MountTransformer::class))
            ->toArray();
    }

    /**
     * Attaches several mounts in a single call.
     *
     * All or nothing: if one of the requested mounts is not eligible, none are attached
     * so the caller never ends up in a silent partial state.
     *
     * @throws Throwable
     */
    public function storeBulk(StoreServerMountsRequest $request, Server $server): array
    {
        $ids = $request->mountIds();

        $this->assertMountsAreEligible($server, $ids);

        $this->attachMounts($server, $ids);
        $this->syncWithDaemon($server);

        // Read back from the relation so the response describes the actual state of the
        // server, including mounts that were already attached.
        return $this->fractal->collection($server->mounts()->get())
            ->transformWith($this->getTransformer(MountTransformer::class))
            ->toArray();
    }

    /**
     * Detaches a mount from a server.
     *
     * Idempotent: detaching a mount that is not attached also returns a 204.
     */
    public function delete(ServerWriteRequest $request, Server $server, Mount $mount): Response
    {
        MountServer::query()
            ->where('server_id', $server->id)
            ->where('mount_id', $mount->id)
            ->delete();

        $this->syncWithDaemon($server);

        return $this->returnNoContent();
    }

    /**
     * Writes the missing mount_server rows.
     *
     * Idempotent: mounts that are already attached are skipped, the table has a unique
     * index on (server_id, mount_id).
     *
     * @param int[] $mountIds
     *
     * @throws Throwable
     */
    protected function attachMounts(Server $server, array $mountIds): void
    {
        $this->connection->transaction(function () use ($server, $mountIds) {
            $existing = MountServer::query()
                ->where('server_id', $server->id)
                ->whereIn('mount_id', $mountIds)
                ->pluck('mount_id')
                ->all();

            foreach (array_diff($mountIds, $existing) as $mountId) {
                // Same write as ServersController::addMount(). forceFill() is needed
                // because the model has no mass assignable fields.
                (new MountServer())->forceFill([
                    'server_id' => $server->id,
                    'mount_id' => $mountId,
                ])->saveOrFail();
            }
        });
    }

    /**
     * Checks that every mount is allowed for both the node and the egg of the server.
     *
     * This is the filter used by MountRepository::getMountListForServer(), which feeds
     * the dropdown in the admin area, narrowed down to the requested mounts.
     *
     * @param int[] $mountIds
     *
     * @throws MountNotEligibleException
     */
    protected function assertMountsAreEligible(Server $server, array $mountIds): void
    {
        $eligible = Mount::query()
            ->whereIn('mounts.id', $mountIds)
            ->whereHas('eggs', function ($q) use ($server) {
                $q->where('id', '=', $server->egg_id);
            })
            ->whereHas('nodes', function ($q) use ($server) {
                $q->where('id', '=', $server->node_id);
            })
            ->pluck('mounts.id')
            ->all();

        $rejected = array_diff($mountIds, $eligible);

        if (!empty($rejected)) {
            throw new MountNotEligibleException(sprintf(
                'The following mounts are not available to this server: %s. A mount must be assigned to both the server\'s node (#%d) and its egg (#%d) in the admin area before it can be attached.',
                implode(', ', $rejected),
                $server->node_id,
                $server->egg_id
            ));
        }
    }

    /**
     * Pushes the updated configuration to Wings.
     *
     * FRAGILE — re-check this after every panel upgrade.
     *
     * DaemonServerRepository::sync() is what the panel itself uses in
     * BuildModificationService. It makes Wings pull the server configuration again,
     * which carries the "mounts" key built by ServerConfigurationStructureService.
     *
     * Two things to keep in mind:
     *   1. Without this call the row exists in the database but Wings keeps the old
     *      configuration in memory until its next reload.
     *   2. Even after the sync, Docker only applies a bind mount when the container is
     *      created, so the mount shows up in /home/container once the container has
     *      been recreated (stop then start, or reinstall).
     *
     * A failed sync is not fatal, same as BuildModificationService: Wings fetches a
     * fresh configuration when the server boots anyway.
     */
    protected function syncWithDaemon(Server $server): void
    {
        try {
            $this->daemonServerRepository->setServer($server)->sync();
        } catch (DaemonConnectionException $exception) {
            Log::warning($exception, ['server_id' => $server->id]);
        }
    }
}
