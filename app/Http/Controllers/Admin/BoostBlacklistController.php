<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\BoostBlacklistEntry;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Boost\ServerBoostService;
use Pterodactyl\Services\Boost\BoostBlacklistService;

class BoostBlacklistController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private BoostBlacklistService $blacklist,
        private ServerBoostService $boost,
    ) {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $token = $this->boost->token();

        return view('admin.boost.blacklist', [
            'entries' => BoostBlacklistEntry::query()
                ->with('creator:id,username')
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('value', 'like', '%' . addcslashes($search, '%_\\') . '%')
                    ->orWhere('reason', 'like', '%' . addcslashes($search, '%_\\') . '%')))
                ->orderByDesc('id')
                ->paginate(100)
                ->withQueryString(),
            'activeCount' => BoostBlacklistEntry::query()->active()->count(),
            'search' => $search,
            'types' => BoostBlacklistEntry::TYPES,
            'apiUrl' => $token ? route('api:boost.blacklist', $token) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => 'required|in:' . implode(',', array_keys(BoostBlacklistEntry::TYPES)),
            'value' => 'required|string|max:255',
            'reason' => 'nullable|string|max:191',
            'days' => 'nullable|integer|min:1|max:3650',
        ]);

        $entry = $this->blacklist->add($data['type'], $data['value'], $data['reason'] ?? null, $data['days'] ?? null, $request->user()?->id);
        $this->alert->success(sprintf(
            '%s %s na blacklist%s.',
            BoostBlacklistEntry::TYPES[$entry->type],
            $entry->value,
            $entry->expires_at ? ' até ' . $entry->expires_at->copy()->setTimezone(config('app.stats_timezone', 'UTC'))->format('d/m/Y H:i') : ''
        ))->flash();

        // Buttons on the Server Boost dashboard come back to it.
        $back = (string) $request->input('back', '');

        return str_starts_with($back, route('admin.boost'))
            ? redirect()->to($back)
            : redirect()->route('admin.boost.blacklist');
    }

    public function delete(BoostBlacklistEntry $entry): RedirectResponse
    {
        $entry->delete();
        $this->alert->success(BoostBlacklistEntry::TYPES[$entry->type] . " {$entry->value} saiu da blacklist.")->flash();

        return redirect()->route('admin.boost.blacklist');
    }
}
