<?php

namespace Pterodactyl\Services\HgHlds;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Issues the signed licenses requested by the hgHLDS engine when it boots.
 */
class ActivationService
{
    public const SETTING_ALLOWLIST = 'settings::hghlds:allowlist';
    public const SETTING_PRIVATE_KEY = 'settings::hghlds:private_key';

    public function __construct(
        private SettingsRepositoryInterface $settings,
        private Encrypter $encrypter,
    ) {
    }

    /**
     * The address of whoever is connecting. Deliberately ignores the trusted proxy
     * setup: TRUSTED_PROXIES=* lets anyone reaching the origin directly choose the
     * value of X-Forwarded-For, and this address is all that guards the endpoint.
     */
    public function clientIp(Request $request): ?string
    {
        $remote = (string) $request->server->get('REMOTE_ADDR');

        if (!IpUtils::checkIp($remote, config('hghlds.cloudflare_ranges'))) {
            return $remote;
        }

        $forwarded = filter_var($request->headers->get('CF-Connecting-IP'), FILTER_VALIDATE_IP);

        return $forwarded === false ? null : $forwarded;
    }

    public function isAllowed(string $ip): bool
    {
        return IpUtils::checkIp($ip, $this->allowlist());
    }

    /**
     * @return string[] IP addresses and CIDR ranges
     */
    public function allowlist(): array
    {
        return array_values(array_filter(explode("\n", (string) $this->settings->get(self::SETTING_ALLOWLIST, ''))));
    }

    /**
     * @return string|null the response body, or null when no private key is configured
     */
    public function sign(string $nonce): ?string
    {
        $secretKey = $this->secretKey();
        if ($secretKey === null) {
            return null;
        }

        $expires = now()->addSeconds(config('hghlds.license_ttl'))->getTimestamp();
        $payload = base64_encode(sprintf('nonce=%s;exp=%d;feat=%d', $nonce, $expires, config('hghlds.features')));

        // The engine verifies the base64 text, not the decoded payload.
        return $payload . '.' . base64_encode(sodium_crypto_sign_detached($payload, $secretKey));
    }

    public function publicKeyHex(): ?string
    {
        $secretKey = $this->secretKey();

        return $secretKey === null ? null : bin2hex(sodium_crypto_sign_publickey_from_secretkey($secretKey));
    }

    /**
     * Accepts a 32-byte seed or a 64-byte secret key (seed + public key), in hex.
     *
     * @return string|null the 64-byte secret key, or null when the input is not a valid key
     */
    public static function parseSecretKey(string $hex): ?string
    {
        if (preg_match('/\A(?:[0-9a-fA-F]{64}|[0-9a-fA-F]{128})\z/', $hex) !== 1) {
            return null;
        }

        $bytes = sodium_hex2bin($hex);
        $derived = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(substr($bytes, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES)));

        return strlen($bytes) === SODIUM_CRYPTO_SIGN_SEEDBYTES || hash_equals($derived, $bytes) ? $derived : null;
    }

    private function secretKey(): ?string
    {
        $stored = $this->settings->get(self::SETTING_PRIVATE_KEY, '');

        return empty($stored) ? null : sodium_hex2bin($this->encrypter->decrypt($stored));
    }
}
