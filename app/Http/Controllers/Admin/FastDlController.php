<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Services\FastDl\FastDlBucketAuditService;
use Pterodactyl\Models\FastDlNode;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\FastDl\FastDlNodeUpdateService;
use Pterodactyl\Services\FastDl\FastDlNodeCreationService;
use Pterodactyl\Services\FastDl\FastDlNodeDeletionService;
use Pterodactyl\Contracts\Repository\LocationRepositoryInterface;
use Pterodactyl\Contracts\Repository\FastDlNodeRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\FastDl\FastDlNodeFormRequest;

class FastDlController extends Controller
{
    /**
     * FastDlController constructor.
     */
    public function __construct(
        protected AlertsMessageBag $alert,
        protected FastDlNodeCreationService $creationService,
        protected FastDlNodeDeletionService $deletionService,
        protected FastDlNodeRepositoryInterface $repository,
        protected FastDlNodeUpdateService $updateService,
        protected LocationRepositoryInterface $locationRepository
    ) {
    }

    /**
     * Bucket folders mapped to servers (de→para).
     */
    public function audit(FastDlNode $node, FastDlBucketAuditService $service): View
    {
        try {
            $audit = $service->audit($node);
            $error = null;
        } catch (\Throwable $exception) {
            $audit = ['root' => '', 'folders' => []];
            $error = 'Não foi possível ler o bucket: ' . $exception->getMessage();
        }

        return view('admin.fastdl.audit', ['node' => $node, 'audit' => $audit, 'error' => $error]);
    }

    /**
     * Removes unused server folders from the bucket.
     */
    public function clean(Request $request, FastDlNode $node, FastDlBucketAuditService $service): RedirectResponse
    {
        $folders = array_values(array_filter((array) $request->input('folders', []), 'is_string'));

        try {
            $deleted = $service->clean($node, $folders);
            $this->alert->success(sprintf('%d pasta(s) removida(s) do bucket (%d arquivos).', count($deleted), array_sum($deleted)))->flash();
        } catch (\Throwable $exception) {
            $this->alert->danger('Falha ao limpar o bucket: ' . $exception->getMessage())->flash();
        }

        return redirect()->route('admin.fastdl.audit', $node->id);
    }

    /**
     * Display the FastDL node index page.
     */
    public function index(): View
    {
        return view('admin.fastdl.index', [
            'nodes' => $this->repository->getBuilder()->with('location')->paginate(50),
        ]);
    }

    /**
     * Display the FastDL node creation page.
     */
    public function create(): View
    {
        return view('admin.fastdl.new', [
            'locations' => $this->locationRepository->all(),
        ]);
    }

    /**
     * Store a new FastDL node.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function store(FastDlNodeFormRequest $request): RedirectResponse
    {
        $this->creationService->handle($request->normalize());
        $this->alert->success('FastDL Node created successfully.')->flash();

        return redirect()->route('admin.fastdl');
    }

    /**
     * Display a specific FastDL node.
     */
    public function view(FastDlNode $node): View
    {
        return view('admin.fastdl.view', [
            'node' => $node,
            'locations' => $this->locationRepository->all(),
        ]);
    }

    /**
     * Update a FastDL node.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(FastDlNodeFormRequest $request, FastDlNode $node): RedirectResponse
    {
        $this->updateService->handle($node, $request->normalize());
        $this->alert->success('FastDL Node updated successfully.')->flash();

        return redirect()->route('admin.fastdl.view', $node->id);
    }

    /**
     * Delete a FastDL node.
     */
    public function delete(FastDlNode $node): RedirectResponse
    {
        $this->deletionService->handle($node);
        $this->alert->success('FastDL Node deleted successfully.')->flash();

        return redirect()->route('admin.fastdl');
    }
}
