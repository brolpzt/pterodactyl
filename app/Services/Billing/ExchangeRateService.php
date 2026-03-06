<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;

class ExchangeRateService
{
    private const OPEN_API_URL = 'https://open.er-api.com/v6/latest/USD';
    private const API_URL = 'https://v6.exchangerate-api.com/v6/%s/latest/USD';

    public function __construct(
        private SettingsRepository $settings
    ) {
    }

    /**
     * Fetch exchange rates from API and store in settings.
     * Returns rates array (USD => 1, EUR => x, BRL => x) or null on failure.
     */
    public function fetchAndStore(): ?array
    {
        $url = $this->getApiUrl();
        if (!$url) {
            Log::warning('Billing: No exchange rate API URL configured.');

            return null;
        }

        try {
            $response = Http::timeout(10)->get($url);
            $data = $response->json();

            if (!$response->successful() || ($data['result'] ?? null) !== 'success') {
                Log::warning('Billing: Exchange rate API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $rates = $data['rates'] ?? $data['conversion_rates'] ?? null;
            if (!$rates || !is_array($rates)) {
                Log::warning('Billing: Exchange rate API returned invalid rates.');

                return null;
            }

            // Store only the currencies we need (USD, EUR, BRL)
            $stored = [
                'USD' => 1.0,
                'EUR' => (float) ($rates['EUR'] ?? 0),
                'BRL' => (float) ($rates['BRL'] ?? 0),
                'updated_at' => now()->toIso8601String(),
            ];

            if ($stored['EUR'] <= 0 || $stored['BRL'] <= 0) {
                Log::warning('Billing: Exchange rate API missing EUR or BRL.', ['rates' => $stored]);

                return null;
            }

            $this->settings->set(config('billing.exchange_rates_key'), json_encode($stored));

            return $stored;
        } catch (\Throwable $e) {
            Log::error('Billing: Exchange rate fetch failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get stored exchange rates. Returns null if not yet fetched.
     */
    public function getRates(): ?array
    {
        $json = $this->settings->get(config('billing.exchange_rates_key'));
        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    private function getApiUrl(): ?string
    {
        $key = config('billing.exchange_rate_api_key');
        if ($key) {
            return sprintf(self::API_URL, $key);
        }

        return self::OPEN_API_URL;
    }
}
