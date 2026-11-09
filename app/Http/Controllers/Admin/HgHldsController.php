<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Services\HgHlds\ActivationService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class HgHldsController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private ActivationService $service,
        private SettingsRepositoryInterface $settings,
        private Encrypter $encrypter,
    ) {
    }

    public function index(): View
    {
        return view('admin.hghlds.index', [
            'allowlist' => implode("\n", $this->service->allowlist()),
            'publicKey' => $this->service->publicKeyHex(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'allowlist' => 'nullable|string|max:10000',
            'private_key' => 'nullable|string|max:128',
        ]);

        $entries = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,]+/', $data['allowlist'] ?? '')))));
        foreach ($entries as $entry) {
            if (!$this->isIpOrRange($entry)) {
                throw ValidationException::withMessages(['allowlist' => "Endereço inválido: {$entry}"]);
            }
        }

        if (!empty($data['private_key'])) {
            $secretKey = ActivationService::parseSecretKey($data['private_key']);
            if ($secretKey === null) {
                throw ValidationException::withMessages(['private_key' => 'Chave inválida: use a semente (64 hex) ou a chave secreta Ed25519 de 64 bytes (128 hex).']);
            }

            $this->settings->set(ActivationService::SETTING_PRIVATE_KEY, $this->encrypter->encrypt(bin2hex($secretKey)));
        }

        $this->settings->set(ActivationService::SETTING_ALLOWLIST, implode("\n", $entries));

        $this->alert->success('Configurações do hgHLDS salvas.')->flash();

        return redirect()->route('admin.hghlds');
    }

    private function isIpOrRange(string $entry): bool
    {
        [$ip, $mask] = array_pad(explode('/', $entry, 2), 2, null);

        $flag = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false ? 32
            : (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : null);

        if ($flag === null) {
            return false;
        }

        return $mask === null || (ctype_digit($mask) && (int) $mask <= $flag);
    }
}
