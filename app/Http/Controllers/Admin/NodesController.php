<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Illuminate\Http\Response;
use Pterodactyl\Models\Allocation;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodes\NodeUpdateService;
use Illuminate\Cache\Repository as CacheRepository;
use Pterodactyl\Services\Nodes\NodeCreationService;
use Pterodactyl\Services\Nodes\NodeDeletionService;
use Pterodactyl\Services\Allocations\AssignmentService;
use Pterodactyl\Services\Helpers\SoftwareVersionService;
use Pterodactyl\Http\Requests\Admin\Node\NodeFormRequest;
use Pterodactyl\Contracts\Repository\NodeRepositoryInterface;
use Pterodactyl\Contracts\Repository\ServerRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Node\AllocationFormRequest;
use Pterodactyl\Services\Allocations\AllocationDeletionService;
use Pterodactyl\Contracts\Repository\LocationRepositoryInterface;
use Pterodactyl\Contracts\Repository\AllocationRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Node\AllocationAliasFormRequest;
use Pterodactyl\Services\Gcore\GcoreFirewallSyncService;
use Pterodactyl\Exceptions\DisplayException;

class NodesController extends Controller
{
    /**
     * NodesController constructor.
     */
    public function __construct(
        protected AlertsMessageBag $alert,
        protected AllocationDeletionService $allocationDeletionService,
        protected AllocationRepositoryInterface $allocationRepository,
        protected AssignmentService $assignmentService,
        protected CacheRepository $cache,
        protected NodeCreationService $creationService,
        protected NodeDeletionService $deletionService,
        protected LocationRepositoryInterface $locationRepository,
        protected NodeRepositoryInterface $repository,
        protected ServerRepositoryInterface $serverRepository,
        protected NodeUpdateService $updateService,
        protected SoftwareVersionService $versionService,
        protected ViewFactory $view,
        protected GcoreFirewallSyncService $gcoreFirewallSync,
    ) {
    }

    /**
     * Displays create new node page.
     */
    public function create(): View|RedirectResponse
    {
        $locations = $this->locationRepository->all();
        if (count($locations) < 1) {
            $this->alert->warning(trans('admin/node.notices.location_required'))->flash();

            return redirect()->route('admin.locations');
        }

        return view('admin.nodes.new', ['locations' => $locations]);
    }

    /**
     * Post controller to create a new node on the system.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(NodeFormRequest $request): RedirectResponse
    {
        $node = $this->creationService->handle($request->normalize());
        $this->alert->info(trans('admin/node.notices.node_created'))->flash();

        return redirect()->route('admin.nodes.view.allocation', $node->id);
    }

    /**
     * Updates settings for a node.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function updateSettings(NodeFormRequest $request, Node $node): RedirectResponse
    {
        $this->updateService->handle($node, $request->normalize(), $request->input('reset_secret') === 'on');
        $this->alert->success(trans('admin/node.notices.node_updated'))->flash();

        return redirect()->route('admin.nodes.view.settings', $node->id)->withInput();
    }

    /**
     * Removes a single allocation from a node.
     *
     * @throws \Pterodactyl\Exceptions\Service\Allocation\ServerUsingAllocationException
     */
    public function allocationRemoveSingle(int $node, Allocation $allocation): Response
    {
        $model = Allocation::query()->find($allocation->id) ?? $allocation;
        $ip = $model->ip;
        $wasProtected = (bool) $model->gcore_protected;

        $this->allocationDeletionService->handle($model);

        if ($wasProtected && $ip) {
            $this->syncGcoreIpQuietly($node, $ip);
        }

        return response('', 204);
    }

    /**
     * Removes multiple individual allocations from a node.
     *
     * @throws \Pterodactyl\Exceptions\Service\Allocation\ServerUsingAllocationException
     */
    public function allocationRemoveMultiple(Request $request, int $node): Response
    {
        $allocations = $request->input('allocations');
        $ipsToSync = [];
        foreach ($allocations as $rawAllocation) {
            $model = Allocation::query()->find($rawAllocation['id']);
            if (!$model) {
                continue;
            }
            if ($model->gcore_protected) {
                $ipsToSync[$model->ip] = true;
            }
            $this->allocationDeletionService->handle($model);
        }

        foreach (array_keys($ipsToSync) as $ip) {
            $this->syncGcoreIpQuietly($node, (string) $ip);
        }

        return response('', 204);
    }

    /**
     * Remove all allocations for a specific IP at once on a node.
     */
    public function allocationRemoveBlock(Request $request, int $node): RedirectResponse
    {
        $ip = (string) $request->input('ip');
        $hadProtected = Allocation::query()
            ->where('node_id', $node)
            ->where('ip', $ip)
            ->where('gcore_protected', true)
            ->exists();

        $this->allocationRepository->deleteWhere([
            ['node_id', '=', $node],
            ['server_id', '=', null],
            ['ip', '=', $ip],
        ]);

        if ($hadProtected) {
            $this->syncGcoreIpQuietly($node, $ip);
        }

        $this->alert->success(trans('admin/node.notices.unallocated_deleted', ['ip' => htmlspecialchars($ip)]))
            ->flash();

        return redirect()->route('admin.nodes.view.allocation', $node);
    }

    /**
     * Sets an alias for a specific allocation on a node.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function allocationSetAlias(AllocationAliasFormRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->allocationRepository->update($request->input('allocation_id'), [
            'ip_alias' => (empty($request->input('alias'))) ? null : $request->input('alias'),
        ]);

        return response('', 204);
    }

    /**
     * Creates new allocations on a node.
     *
     * @throws \Pterodactyl\Exceptions\Service\Allocation\CidrOutOfRangeException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\InvalidPortMappingException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\PortOutOfRangeException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\TooManyPortsInRangeException
     */
    public function createAllocation(AllocationFormRequest $request, Node $node): RedirectResponse
    {
        $data = $request->normalize();
        $data['gcore_protected'] = $node->gcore_enabled && $request->boolean('gcore_protected');

        $this->assignmentService->handle($node, $data);
        $this->alert->success(trans('admin/node.notices.allocations_added'))->flash();

        if (!empty($data['gcore_protected'])) {
            try {
                $resolvedIp = gethostbyname((string) $data['allocation_ip']);
                $msg = $this->gcoreFirewallSync->syncIp($node, $resolvedIp);
                $this->alert->info('Gcore: ' . $msg)->flash();
            } catch (DisplayException $e) {
                $this->alert->warning('Allocations criadas, mas sync Gcore falhou: ' . $e->getMessage())->flash();
            }
        }

        return redirect()->route('admin.nodes.view.allocation', $node->id);
    }

    /**
     * Toggle whether an allocation should open its port on the Gcore ACL.
     */
    public function allocationSetGcoreProtected(Request $request, Node $node): Response
    {
        $allocation = Allocation::query()
            ->where('node_id', $node->id)
            ->where('id', (int) $request->input('allocation_id'))
            ->firstOrFail();

        if (!$node->gcore_enabled) {
            return response(['error' => 'Este node não está marcado como Gcore.'], 422);
        }

        $allocation->gcore_protected = $request->boolean('gcore_protected');
        $allocation->save();

        try {
            $msg = $this->gcoreFirewallSync->syncIp($node, $allocation->ip);

            return response(['message' => $msg], 200);
        } catch (DisplayException $e) {
            return response(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Push all protected allocation ports for this node to Gcore ACL profiles.
     */
    public function syncGcoreFirewall(Node $node): RedirectResponse
    {
        try {
            $lines = $this->gcoreFirewallSync->syncNode($node);
            $this->alert->success('Gcore sync: ' . implode(' · ', $lines))->flash();
        } catch (DisplayException $e) {
            $this->alert->danger($e->getMessage())->flash();
        }

        return redirect()->route('admin.nodes.view.allocation', $node->id);
    }

    private function syncGcoreIpQuietly(int|Node $node, string $ip): void
    {
        $model = $node instanceof Node ? $node : Node::query()->find($node);
        if (!$model || !$model->gcore_enabled) {
            return;
        }

        try {
            $this->gcoreFirewallSync->syncIp($model, $ip);
        } catch (\Throwable) {
            // Deletion must succeed even if Gcore is temporarily unreachable.
        }
    }

    /**
     * Deletes a node from the system.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function delete(int|Node $node): RedirectResponse
    {
        $this->deletionService->handle($node);
        $this->alert->success(trans('admin/node.notices.node_deleted'))->flash();

        return redirect()->route('admin.nodes');
    }
}
