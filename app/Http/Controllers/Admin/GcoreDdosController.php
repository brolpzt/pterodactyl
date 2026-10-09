<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Services\Gcore\GcoreAclHelper;

class GcoreDdosController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        $error = null;
        $profiles = [];
        $configured = (string) config('gcore.api_key', '') !== '';

        if ($configured) {
            try {
                $profiles = GcoreClient::fromConfig()->listProfiles();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin.gcore.index', [
            'profiles' => $profiles,
            'error' => $error,
            'configured' => $configured,
        ]);
    }

    public function show(int $profile): View|RedirectResponse
    {
        try {
            $client = GcoreClient::fromConfig();
            $data = $client->getProfile($profile);
            $form = GcoreAclHelper::extractProfileFormData($data);

            return view('admin.gcore.profile', [
                'profile' => $data,
                'form' => $form,
                'policies' => GcoreClient::POLICIES,
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $this->alert->danger($e->getMessage())->flash();

            return redirect()->route('admin.gcore');
        }
    }

    public function update(Request $request, int $profile): RedirectResponse
    {
        try {
            $client = GcoreClient::fromConfig();
            $data = $client->getProfile($profile);

            $rate = [
                'low' => max(1, min(50, (int) $request->input('rate_low', 50))),
                'medium' => max(50, min(150, (int) $request->input('rate_medium', 150))),
                'high' => max(150, min(300, (int) $request->input('rate_high', 300))),
                'geo' => max(1, min(300, (int) $request->input('rate_geo', 300))),
            ];

            $geoRaw = trim((string) $request->input('geoip_list', ''));
            $geoip = $geoRaw === '' ? [] : preg_split('/[\s,;]+/', strtoupper($geoRaw));
            $geoip = array_values(array_filter($geoip ?: []));

            $acl = GcoreAclHelper::aclFromPost($request->all());
            $payload = $client->buildUpdatePayload($data, $rate, $geoip, $acl);
            $updated = $client->updateProfile($profile, $payload);

            $this->alert->success(
                'Enviado à Gcore · ' . ($updated['status']['status'] ?? 'OK') . ' (propagação ~2–5 min)'
            )->flash();
        } catch (\Throwable $e) {
            $this->alert->danger($e->getMessage())->flash();
        }

        return redirect()->route('admin.gcore.profile', $profile);
    }
}
