<?php

namespace Chredeur\PterodactylApiAddon\Http\Controllers;

use Carbon\CarbonImmutable;
use Grpc\Server;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\ServerTransfer;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Repositories\Eloquent\ServerRepository;
use Pterodactyl\Repositories\Wings\DaemonTransferRepository;
use Pterodactyl\Contracts\Repository\AllocationRepositoryInterface;
use Pterodactyl\Http\Requests\Api\Application\Servers\ServerWriteRequest;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Illuminate\Support\Facades\Log;
use Throwable;

class ServerTransfertApplicationController extends ApplicationApiController
{
    /**
     * ServerTransferApplicationController constructor.
     */
    public function __construct(
        private AllocationRepositoryInterface $allocationRepository,
        private ConnectionInterface           $connection,
        private DaemonTransferRepository      $daemonTransferRepository,
        private NodeJWTService                $nodeJWTService,
        private NodeRepository                $nodeRepository,
        private ServerRepository              $serverRepository
    ){
        parent::__construct();
    }

    /**
     * Starts a transfer of a server to a new node.
     *
     * @throws Throwable
     */
    public function transfer(ServerWriteRequest $request): JsonResponse
    {
        $validatedData = $request->validate([
            'node_id' => 'required|exists:nodes,id',
            'server_uuid' => 'required|exists:servers,uuid',
            'allocation_id' => 'required|bail|unique:servers|exists:allocations,id',
            'allocation_additional' => 'nullable',
        ]);

        $node_id = $validatedData['node_id'];
        $server_uuid = $validatedData['server_uuid'];
        $allocation_id = intval($validatedData['allocation_id']);
        $additional_allocations = array_map('intval', $validatedData['allocation_additional'] ?? []);

        Log::channel('daily')->info($node_id);
        Log::channel('daily')->info($allocation_id);
        // Check if the node is viable for the transfer.
        $node = $this->nodeRepository->getNodeWithResourceUsage($node_id);
        $server = $this->serverRepository->getByUuid($server_uuid);
        Log::channel('daily')->info($node->memory);
        Log::channel('daily')->info($server->memory);
        if (!$node->isViable($server->memory, $server->disk)) {
            return new JsonResponse(['status_code' => 'Bad Request', 'status' => 400, 'detail' => 'The node you have chosen is not viable.'], 400);
        }

        $server->validateTransferState();

        $this->connection->transaction(function () use ($server, $node_id, $allocation_id, $additional_allocations) {
            // Create a new ServerTransfer entry.
            $transfer = new ServerTransfer();

            $transfer->server_id = $server->id;
            $transfer->old_node = $server->node_id;
            $transfer->new_node = $node_id;
            $transfer->old_allocation = $server->allocation_id;
            $transfer->new_allocation = $allocation_id;
            $transfer->old_additional_allocations = $server->allocations->where('id', '!=', $server->allocation_id)->pluck('id');
            $transfer->new_additional_allocations = $additional_allocations;

            $transfer->save();

            // Add the allocations to the server, so they cannot be automatically assigned while the transfer is in progress.
            $this->assignAllocationsToServer($server, $node_id, $allocation_id, $additional_allocations);

            // Generate a token for the destination node that the source node can use to authenticate with.
            $token = $this->nodeJWTService
                ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
                ->setSubject($server->uuid)
                ->handle($transfer->newNode, $server->uuid, 'sha256');

            // Notify the source node of the pending outgoing transfer.
            $this->daemonTransferRepository->setServer($server)->notify($transfer->newNode, $token);

            return $transfer;
        });

        return JsonResponse::create([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Assigns the specified allocations to the specified server.
     */
    private function assignAllocationsToServer(Server $server, int $node_id, int $allocation_id, array $additional_allocations)
    {
        $allocations = $additional_allocations;
        $allocations[] = $allocation_id;

        $unassigned = $this->allocationRepository->getUnassignedAllocationIds($node_id);

        $updateIds = [];
        foreach ($allocations as $allocation) {
            if (!in_array($allocation, $unassigned)) {
                continue;
            }

            $updateIds[] = $allocation;
        }

        if (!empty($updateIds)) {
            $this->allocationRepository->updateWhereIn('id', $updateIds, ['server_id' => $server->id]);
        }
    }
}
