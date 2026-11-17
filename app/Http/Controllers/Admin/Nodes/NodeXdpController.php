<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\XdpSyncLog;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Services\Xdp\XdpProfile;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Xdp\XdpFirewallSyncService;

/**
 * Node tab for the XDP game firewall (game_firewall on the node, through Wings).
 */
class NodeXdpController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private XdpFirewallSyncService $xdp,
    ) {
    }

    public function index(Request $request, Node $node): View
    {
        $desired = $this->xdp->desiredPortsDetailed($node);

        // Dry run: what a sync would change on the node right now (nothing is applied).
        $preview = null;
        $previewError = null;
        try {
            $preview = $this->xdp->syncNode($node, dryRun: true);
        } catch (\Throwable $e) {
            $previewError = $e->getMessage();
        }

        return view('admin.nodes.view.xdp', [
            'node' => $node,
            'rows' => $desired['rows'],
            'conflicts' => $desired['conflicts'],
            'preview' => $preview,
            'previewError' => $previewError,
            'lastSync' => $this->xdp->lastSyncStatus($node),
            'showAll' => $request->boolean('all'),
            'history' => $this->xdp->history($node, !$request->boolean('all'))->withQueryString(),
            'lastReconcile' => $this->xdp->lastReconcile($node),
            'labels' => XdpProfile::LABELS,
        ]);
    }

    public function setEnabled(Request $request, Node $node): RedirectResponse
    {
        $enabled = $request->boolean('xdp_enabled');
        $node->update(['xdp_enabled' => $enabled]);

        $this->alert->success(
            $enabled
                ? 'Firewall XDP habilitado. Confira a prévia e clique em "Sincronizar agora" para aplicar; depois disso o painel mantém o node sincronizado sozinho.'
                : 'Firewall XDP desabilitado: o painel parou de sincronizar. As portas que já estão no node continuam protegidas.'
        )->flash();

        return redirect()->route('admin.nodes.view.xdp', $node->id);
    }

    public function sync(Request $request, Node $node): RedirectResponse
    {
        try {
            $result = $this->xdp->syncNode($node, false, XdpSyncLog::TRIGGER_MANUAL, 'por ' . ($request->user()?->username ?? '?'));
            $this->alert->success(sprintf(
                'Firewall XDP sincronizado: entraram %d, mudaram %d, saíram %d porta(s); %d sem alteração.',
                count($result['added']),
                count($result['updated']),
                count($result['removed']),
                (int) $result['unchanged'],
            ))->flash();
        } catch (\Throwable $e) {
            $this->alert->danger($e->getMessage())->flash();
        }

        return redirect()->route('admin.nodes.view.xdp', $node->id);
    }
}
