<?php

namespace Pterodactyl\Http\Controllers\Admin\Nests;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\PanelAlert;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\EggDdosProfile;
use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\ServerDdosProfileState;
use Pterodactyl\Services\Gcore\DdosProfileService;

/**
 * Egg tab: which Gcore policies the client can pick for the game port, under which names.
 */
class EggDdosProfileController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private DdosProfileService $service)
    {
    }

    public function index(Egg $egg): View
    {
        $egg->load('nest');
        $profiles = $this->service->profilesFor($egg);
        $inUse = ServerDdosProfileState::query()
            ->whereIn('egg_ddos_profile_id', $profiles->pluck('id'))
            ->selectRaw('egg_ddos_profile_id, count(*) as total')
            ->groupBy('egg_ddos_profile_id')
            ->pluck('total', 'egg_ddos_profile_id');

        return view('admin.eggs.ddos', [
            'egg' => $egg,
            'settings' => $this->service->settingsFor($egg),
            'profiles' => $profiles,
            'inUse' => $inUse,
            'defaultPolicy' => $this->service->eggDefaultPolicy($egg),
            'policies' => GcoreClient::POLICIES,
            'alertTypes' => PanelAlert::TYPES,
        ]);
    }

    public function update(Request $request, Egg $egg): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'cooldown_minutes' => 'required|integer|between:0,1440',
            'options' => 'sometimes|array|max:20',
            'options.*.id' => 'nullable|integer',
            'options.*.delete' => 'sometimes|boolean',
            'options.*.policy' => 'nullable|string|max:64',
            'options.*.label' => 'nullable|string|max:64',
            'options.*.alert_type' => 'nullable|string|in:' . implode(',', array_keys(PanelAlert::TYPES)),
            'options.*.alert_title' => 'nullable|string|max:191',
            'options.*.alert_message' => 'nullable|string|max:2000',
        ]);

        $existing = $this->service->profilesFor($egg)->keyBy('id');
        $rows = [];
        $deleted = [];
        foreach (array_values($data['options'] ?? []) as $index => $row) {
            $id = isset($row['id']) ? (int) $row['id'] : null;
            if ($id !== null && !$existing->has($id)) {
                continue;
            }
            if (!empty($row['delete'])) {
                if ($id !== null) {
                    $deleted[] = $id;
                }
                continue;
            }

            $policy = trim((string) ($row['policy'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($id === null && $policy === '' && $label === '') {
                continue; // empty "new option" row
            }
            if (!in_array($policy, GcoreClient::POLICIES, true)) {
                return $this->fail($egg, 'Opção ' . ($index + 1) . ': escolha um perfil da Gcore válido.');
            }
            if ($label === '') {
                return $this->fail($egg, 'Opção ' . ($index + 1) . ': informe o nome que o cliente verá.');
            }
            if (strcasecmp($label, $policy) === 0) {
                return $this->fail($egg, 'Opção ' . ($index + 1) . ': o nome para o cliente não pode ser igual ao nome do perfil da Gcore.');
            }

            $rows[] = [
                'id' => $id,
                'policy' => $policy,
                'label' => $label,
                'alert_type' => $row['alert_type'] ?? 'info',
                'alert_title' => trim((string) ($row['alert_title'] ?? '')) ?: null,
                'alert_message' => trim((string) ($row['alert_message'] ?? '')) ?: null,
                'sort_order' => $index,
            ];
        }

        $enabled = $request->boolean('enabled');
        if ($enabled && $rows === []) {
            return $this->fail($egg, 'Cadastre pelo menos uma opção antes de habilitar a mudança pelo cliente.');
        }

        $settings = $this->service->settingsFor($egg);
        $wasEnabled = $settings->exists && $settings->enabled;
        $reverted = 0;
        $repointed = 0;

        DB::transaction(function () use ($egg, $settings, $enabled, $data, $rows, $deleted, $existing, $wasEnabled, &$reverted, &$repointed) {
            $reason = "egg {$egg->name} (#{$egg->id}): opções anti-DDoS alteradas";

            // Servers on removed options, or on any option when the feature is turned off, go back to the egg default.
            if ($wasEnabled && !$enabled) {
                $reverted += $this->service->revertEggServers($egg, $reason);
            } elseif ($deleted !== []) {
                $reverted += $this->service->revertEggServers($egg, $reason, $deleted);
            }
            EggDdosProfile::query()->whereIn('id', $deleted)->delete();

            foreach ($rows as $row) {
                $id = $row['id'];
                unset($row['id']);
                if ($id === null) {
                    EggDdosProfile::query()->create($row + ['egg_id' => $egg->id]);
                    continue;
                }

                /** @var EggDdosProfile $profile */
                $profile = $existing->get($id);
                $oldPolicy = $profile->policy;
                $profile->fill($row)->save();
                if ($oldPolicy !== $profile->policy && $enabled) {
                    $repointed += $this->service->repointProfile($profile, $oldPolicy, $reason);
                }
            }

            $settings->fill(['egg_id' => $egg->id, 'enabled' => $enabled, 'cooldown_minutes' => (int) $data['cooldown_minutes']])->save();
        });

        $message = 'Opções de Anti-DDoS do egg salvas.';
        if ($reverted > 0) {
            $message .= " {$reverted} servidor(es) voltaram ao perfil padrão do egg.";
        }
        if ($repointed > 0) {
            $message .= " {$repointed} servidor(es) seguiram a mudança de perfil da opção.";
        }
        $this->alert->success($message)->flash();

        return redirect()->route('admin.nests.egg.ddos', $egg->id);
    }

    private function fail(Egg $egg, string $message): RedirectResponse
    {
        $this->alert->danger($message)->flash();

        return redirect()->route('admin.nests.egg.ddos', $egg->id)->withInput();
    }
}
