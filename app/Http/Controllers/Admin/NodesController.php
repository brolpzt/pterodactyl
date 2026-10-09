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
use Pterodactyl\Models\NodeGcoreIp;
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
        $this->allocationDeletionService->handle(
            Allocation::query()->find($allocation->id) ?? $allocation
        );

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
        foreach ($allocations as $rawAllocation) {
            $model = Allocation::query()->find($rawAllocation['id']);
            if (!$model) {
                continue;
            }
            $this->allocationDeletionService->handle($model);
        }

        return response('', 204);
    }

    /**
     * Remove all allocations for a specific IP at once on a node.
     */
    public function allocationRemoveBlock(Request $request, int $node): RedirectResponse
    {
        $ip = (string) $request->input('ip');

        $this->allocationRepository->deleteWhere([
            ['node_id', '=', $node],
            ['server_id', '=', null],
            ['ip', '=', $ip],
        ]);

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
        $markIp = $node->gcore_enabled && $request->boolean('gcore_ip');
        $data['gcore_protected'] = $markIp && $request->boolean('gcore_protected');

        $this->assignmentService->handle($node, $data);
        $this->alert->success(trans('admin/node.notices.allocations_added'))->flash();

        if ($markIp) {
            $resolvedIp = gethostbyname((string) $data['allocation_ip']);
            NodeGcoreIp::query()->firstOrCreate([
                'node_id' => $node->id,
                'ip' => $resolvedIp,
            ]);
        }

        return redirect()->route('admin.nodes.view.allocation', $node->id);
    }

    /**
     * Mark/unmark an IP on this node as sitting behind a Gcore DDoS profile (DB only).
     */
    public function allocationSetGcoreIp(Request $request, Node $node): Response
    {
        if (!$node->gcore_enabled) {
            return response(['error' => 'Este node não está marcado como Gcore.'], 422);
        }

        $ip = trim((string) $request->input('ip'));
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return response(['error' => 'IP inválido.'], 422);
        }

        $enabled = $request->boolean('gcore_ip');

        if ($enabled) {
            NodeGcoreIp::query()->firstOrCreate([
                'node_id' => $node->id,
                'ip' => $ip,
            ]);
        } else {
            NodeGcoreIp::query()
                ->where('node_id', $node->id)
                ->where('ip', $ip)
                ->delete();

            Allocation::query()
                ->where('node_id', $node->id)
                ->where('ip', $ip)
                ->where('gcore_protected', true)
                ->update(['gcore_protected' => false]);
        }

        return response([
            'message' => $enabled
                ? 'IP marcado como Gcore. Use Sync portas para enviar à API.'
                : 'IP removido do Gcore. Use Sync portas para atualizar a API.',
        ], 200);
    }

    /**
     * Toggle whether an allocation should open its port on the Gcore ACL (DB only).
     */
    public function allocationSetGcoreProtected(Request $request, Node $node): Response
    {
        $ids = $request->input('allocation_ids');
        if (!is_array($ids) || $ids === []) {
            $single = (int) $request->input('allocation_id');
            $ids = $single > 0 ? [$single] : [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return response(['error' => 'Nenhuma allocation informada.'], 422);
        }

        if (!$node->gcore_enabled) {
            return response(['error' => 'Este node não está marcado como Gcore.'], 422);
        }

        $protected = $request->boolean('gcore_protected');
        $query = Allocation::query()
            ->where('node_id', $node->id)
            ->whereIn('id', $ids);

        if ($protected) {
            $gcoreIps = $node->gcoreIps()->pluck('ip')->all();
            if ($gcoreIps === []) {
                return response(['error' => 'Marque primeiro o IP como protegido no Gcore.'], 422);
            }
            $query->whereIn('ip', $gcoreIps);
        }

        $updated = $query->update(['gcore_protected' => $protected]);

        return response([
            'message' => $protected
                ? "{$updated} porta(s) marcada(s). Use Sync portas para enviar à API."
                : "{$updated} porta(s) desmarcada(s). Use Sync portas para atualizar a API.",
            'updated' => $updated,
        ], 200);
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
