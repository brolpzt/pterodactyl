<?php

namespace Pterodactyl\Services\Billing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Contracts\Billing\PaymentGatewayInterface;
use Pterodactyl\Models\PaymentIntent;
use Pterodactyl\Models\User;

class SicoobPixGateway implements PaymentGatewayInterface
{
    private string $authUrl = 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token';
    private string $baseUrl;
    private string $mode;
    private array $gatewayConfig;
    private array $sandboxConfig;
    private array $productionConfig;

    public function __construct(
        private ExchangeRateService $exchangeRateService,
    )
    {
        $this->gatewayConfig = (array) config('billing.pix', []);
        $this->mode = (string) ($this->gatewayConfig['mode'] ?? 'sandbox');
        $this->sandboxConfig = (array) ($this->gatewayConfig['sandbox'] ?? []);
        $this->productionConfig = (array) ($this->gatewayConfig['production'] ?? []);

        $this->baseUrl = $this->mode === 'production'
            ? 'https://api.sicoob.com.br/pix/api/v2'
            : 'https://sandbox.sicoob.com.br/sicoob/sandbox/pix/api/v2';
    }

    public function createPayment(User $user, float $amount, array $metadata = []): array
    {
        if (!config('billing.pix_enabled', false)) {
            throw new \RuntimeException('PIX is not enabled.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        $intent = PaymentIntent::create([
            'user_id' => $user->id,
            'gateway' => 'pix',
            'external_id' => null,
            'amount' => $amount,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        $paymentData = $this->buildPaymentData($user, $amount, $metadata);
        $result = $this->charge($paymentData);

        $intent->update([
            'external_id' => $result['transaction_id'] ?? $result['tax_id'] ?? null,
            'metadata' => array_merge($metadata, [
                'pix_tax_id' => $result['tax_id'] ?? null,
                'pix_transaction_id' => $result['transaction_id'] ?? null,
                'pix_code' => $result['pix_code'] ?? null,
                'pix_raw' => $result['raw'] ?? null,
            ]),
        ]);

        return [
            'intent_id' => $intent->id,
            'status' => $result['status'] ?? 'pending',
            'gateway' => 'pix',
            'transaction_id' => $result['transaction_id'] ?? null,
            'tax_id' => $result['tax_id'] ?? null,
            'pix_code' => $result['pix_code'] ?? null,
            'qr_code_image' => $result['qr_code_image'] ?? null,
        ];
    }

    public function getIdentifier(): string
    {
        return 'pix';
    }

    public function charge(array $paymentData): array
    {
        $this->validatePaymentData($paymentData);
        $amountBRL = $this->convertToBRL((float) $paymentData['amount'], (string) ($paymentData['currency'] ?? 'USD'));

        if ($amountBRL < 0.01) {
            throw new \InvalidArgumentException('PIX requires minimum amount of R$ 0.01.');
        }

        $accessToken = $this->getAccessToken();
        $taxId = $this->generateTaxId();
        $payload = $this->buildPayload($paymentData, $amountBRL);

        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'client_id' => $this->getClientId(),
        ];

        $httpClient = Http::withHeaders($headers);
        $certPath = $this->resolveCertificatePath($this->getCertificatePfx());
        if ($certPath) {
            $httpClient = $this->configureClientCertificate($httpClient);
        } elseif ($this->mode === 'production') {
            throw new \RuntimeException('Certificado e obrigatorio para modo production.');
        }

        $response = $httpClient->put("{$this->baseUrl}/cobv/{$taxId}", $payload);
        $responseData = $response->json() ?? [];

        if ($response->status() !== 201 || !isset($responseData['txid'])) {
            $errorMessage = $responseData['mensagem']
                ?? $responseData['detail']
                ?? $responseData['httpMessage']
                ?? $responseData['moreInformation']
                ?? 'Unknown error';

            Log::error('Sicoob PIX charge failed', [
                'http_code' => $response->status(),
                'response' => $responseData,
                'tax_id' => $taxId,
            ]);

            throw new \RuntimeException("Sicoob PIX Gateway error: {$errorMessage}");
        }

        $result = [
            'status' => $this->normalizeStatus((string) ($responseData['status'] ?? 'ATIVA')),
            'transaction_id' => $responseData['txid'],
            'tax_id' => $taxId,
            'pix_code' => $responseData['brcode'] ?? $responseData['pixCopiaECola'] ?? null,
            'raw' => [
                'response' => $responseData,
                'http_code' => $response->status(),
                'tax_id' => $taxId,
            ],
        ];

        try {
            $qrCodeImage = $this->getQrCodeImage($taxId);
            $result['qr_code_image'] = $qrCodeImage;
        } catch (\Throwable $exception) {
            Log::debug('Sicoob PIX QR image unavailable', [
                'tax_id' => $taxId,
                'error' => $exception->getMessage(),
            ]);
        }

        return $result;
    }

    public function fetch(string $transactionId): array
    {
        $accessToken = $this->getAccessToken();
        $taxId = preg_replace('/[^0-9]/', '', $transactionId);
        if (strlen((string) $taxId) !== 26) {
            throw new \InvalidArgumentException('Transaction ID (TAX_ID) must be exactly 26 digits.');
        }

        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'Accept' => 'application/json',
            'client_id' => $this->getClientId(),
        ];

        $httpClient = Http::withHeaders($headers);
        $certPath = $this->resolveCertificatePath($this->getCertificatePfx());
        if ($certPath) {
            $httpClient = $this->configureClientCertificate($httpClient);
        } elseif ($this->mode === 'production') {
            throw new \RuntimeException('Certificado e obrigatorio para modo production.');
        }

        $response = $httpClient->get("{$this->baseUrl}/cobv/{$taxId}");
        $responseData = $response->json() ?? [];

        if ($response->status() !== 200 || !isset($responseData['txid'])) {
            throw new \RuntimeException('Failed to fetch PIX transaction.');
        }

        return [
            'status' => $this->normalizeStatus((string) ($responseData['status'] ?? 'ATIVA')),
            'transaction_id' => $responseData['txid'],
            'raw' => $responseData,
            'pix_code' => $responseData['brcode'] ?? $responseData['pixCopiaECola'] ?? null,
        ];
    }

    public function getQrCodeImage(string $transactionId): array
    {
        $accessToken = $this->getAccessToken();
        $taxId = preg_replace('/[^0-9]/', '', $transactionId);
        if (strlen((string) $taxId) !== 26) {
            throw new \InvalidArgumentException('Transaction ID (TAX_ID) must be exactly 26 digits.');
        }

        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'Accept' => 'application/json',
            'client_id' => $this->getClientId(),
        ];

        $httpClient = Http::withHeaders($headers);
        $certPath = $this->resolveCertificatePath($this->getCertificatePfx());
        if ($certPath) {
            $httpClient = $this->configureClientCertificate($httpClient);
        } elseif ($this->mode === 'production') {
            throw new \RuntimeException('Certificado e obrigatorio para modo production.');
        }

        $response = $httpClient->get("{$this->baseUrl}/cobv/{$taxId}/imagem");
        if ($response->status() !== 200) {
            throw new \RuntimeException('Failed to fetch QR Code image.');
        }

        $contentType = (string) $response->header('Content-Type');
        if (str_contains($contentType, 'image/')) {
            $imageBase64 = base64_encode($response->body());

            return [
                'image_base64' => $imageBase64,
                'image_mime_type' => $contentType,
                'image_data_url' => "data:{$contentType};base64,{$imageBase64}",
            ];
        }

        $responseData = $response->json() ?? [];
        if (!isset($responseData['imagem'])) {
            throw new \RuntimeException('Failed to parse QR Code image response.');
        }

        $mimeType = (string) ($responseData['tipo'] ?? 'image/png');
        return [
            'image_base64' => (string) $responseData['imagem'],
            'image_mime_type' => $mimeType,
            'image_data_url' => "data:{$mimeType};base64,{$responseData['imagem']}",
        ];
    }

    private function buildPaymentData(User $user, float $amount, array $metadata): array
    {
        $payerName = trim($user->name) !== '' ? $user->name : $user->username;
        $payerCpf = preg_replace('/[^0-9]/', '', (string) ($metadata['payer_cpf'] ?? $this->gatewayConfig['default_payer_cpf'] ?? ''));

        if (strlen($payerCpf) !== 11) {
            throw new \InvalidArgumentException(
                'Para criar cobranca PIX e necessario CPF valido (11 digitos). Informe payer_cpf no request ou configure BILLING_PIX_DEFAULT_PAYER_CPF.'
            );
        }

        return [
            'amount' => $amount,
            'currency' => (string) config('billing.currency', 'USD'),
            'payer_name' => $payerName,
            'payer_cpf' => $payerCpf,
            'description' => sprintf('Wallet top-up for %s', $user->username),
        ];
    }

    private function validatePaymentData(array $paymentData): void
    {
        if (empty($paymentData['payer_name'])) {
            throw new \InvalidArgumentException('Sicoob PIX Gateway requires payer_name.');
        }

        $cpf = preg_replace('/[^0-9]/', '', (string) ($paymentData['payer_cpf'] ?? ''));
        if (strlen($cpf) !== 11) {
            throw new \InvalidArgumentException('Sicoob PIX Gateway requires valid CPF (11 digits).');
        }
    }

    private function getAccessToken(): string
    {
        if ($this->mode === 'sandbox') {
            $token = (string) ($this->sandboxConfig['bearer_token'] ?? '');
            if ($token !== '') {
                return $token;
            }

            // Sandbox can also authenticate dynamically via OAuth client_credentials.
            return $this->requestOAuthToken(requireCertificate: false);
        }

        return $this->requestOAuthToken(requireCertificate: true);
    }

    private function authenticate(): string
    {
        return $this->requestOAuthToken(requireCertificate: true);
    }

    private function requestOAuthToken(bool $requireCertificate): string
    {
        $clientId = $this->getClientId();
        $clientSecret = $this->getClientSecret();

        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('Sicoob client_id/client_secret not configured.');
        }

        $httpClient = Http::asForm()->withBasicAuth($clientId, $clientSecret);
        $certPath = $this->resolveCertificatePath($this->getCertificatePfx());

        if ($certPath) {
            $httpClient = $this->configureClientCertificate($httpClient);
        } elseif ($requireCertificate) {
            throw new \RuntimeException('Sicoob production certificate is required.');
        }

        $scopes = [
            'cob.read',
            'cob.write',
            'cobv.write',
            'cobv.read',
            'pix.read',
            'pix.write',
            'webhook.read',
            'webhook.write',
        ];

        $response = $httpClient->post($this->authUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'scope' => implode(' ', $scopes),
        ]);

        $responseData = $response->json() ?? [];
        if ($response->status() !== 200 || !isset($responseData['access_token'])) {
            $message = $responseData['error_description'] ?? $responseData['error'] ?? 'unknown';
            throw new \RuntimeException("Failed to authenticate with Sicoob: {$message}");
        }

        return (string) $responseData['access_token'];
    }

    private function buildPayload(array $paymentData, float $amountBRL): array
    {
        return [
            'calendario' => [
                'dataDeVencimento' => now()->addDays(30)->format('Y-m-d'),
                'validadeAposVencimento' => 30,
            ],
            'devedor' => [
                'cpf' => preg_replace('/[^0-9]/', '', (string) $paymentData['payer_cpf']),
                'nome' => (string) $paymentData['payer_name'],
            ],
            'valor' => [
                'original' => number_format($amountBRL, 2, '.', ''),
            ],
            'chave' => $this->getPixKey(),
            'solicitacaoPagador' => substr((string) ($paymentData['description'] ?? 'Pagamento PIX'), 0, 140),
        ];
    }

    private function convertToBRL(float $amount, string $currency): float
    {
        $currency = strtoupper($currency);

        if (strtoupper($currency) === 'BRL') {
            return $amount;
        }

        $rates = $this->exchangeRateService->getRates();
        if (is_array($rates)) {
            $usdRate = (float) ($rates['USD'] ?? 1.0);
            $brlRate = (float) ($rates['BRL'] ?? 0);
            $sourceRate = (float) ($rates[$currency] ?? 0);

            if ($brlRate > 0) {
                // Rates are USD-based (1 USD = X currency).
                if ($currency === 'USD') {
                    return $amount * $brlRate;
                }

                if ($sourceRate > 0) {
                    $amountInUsd = $amount / $sourceRate;
                    return $amountInUsd * $brlRate;
                }
            }
        }

        // Fallback if dynamic rate is unavailable.
        $multiplier = (float) ($this->gatewayConfig['currency_multiplier'] ?? 1);
        return $amount * $multiplier;
    }

    private function generateTaxId(): string
    {
        $timestamp = time();
        $random = random_int(1000000000, 9999999999);
        $suffix = random_int(0, 999999);
        return substr(
            str_pad((string) $timestamp, 10, '0', STR_PAD_LEFT)
            . str_pad((string) $random, 10, '0', STR_PAD_LEFT)
            . str_pad((string) $suffix, 6, '0', STR_PAD_LEFT),
            0,
            26
        );
    }

    private function configureClientCertificate($httpClient)
    {
        $certPath = $this->resolveCertificatePath($this->getCertificatePfx());
        if (!$certPath) {
            throw new \RuntimeException('Certificate not found.');
        }

        $pemFiles = $this->convertPfxToPem($certPath, $this->getCertificatePassphrase());
        if (!$pemFiles || !file_exists($pemFiles['cert']) || !file_exists($pemFiles['key'])) {
            throw new \RuntimeException('Failed to convert PFX certificate to PEM.');
        }

        return $httpClient->withOptions([
            'cert' => $pemFiles['cert'],
            'ssl_key' => $pemFiles['key'],
            'verify' => true,
        ]);
    }

    private function resolveCertificatePath(?string $certPath): ?string
    {
        if (!$certPath) {
            return null;
        }

        if (str_starts_with($certPath, '/')) {
            return file_exists($certPath) ? $certPath : null;
        }

        $resolved = base_path(ltrim($certPath, '/'));
        return file_exists($resolved) ? $resolved : null;
    }

    private function convertPfxToPem(string $pfxPath, ?string $passphrase = null): ?array
    {
        $tempCert = tempnam(sys_get_temp_dir(), 'sicoob_cert_') . '.pem';
        $tempKey = tempnam(sys_get_temp_dir(), 'sicoob_key_') . '.pem';

        $certCmd = sprintf(
            'openssl pkcs12 -legacy -in %s -clcerts -nokeys -out %s -passin pass:%s 2>&1',
            escapeshellarg($pfxPath),
            escapeshellarg($tempCert),
            escapeshellarg($passphrase ?? '')
        );

        $keyCmd = sprintf(
            'openssl pkcs12 -legacy -in %s -nocerts -nodes -out %s -passin pass:%s 2>&1',
            escapeshellarg($pfxPath),
            escapeshellarg($tempKey),
            escapeshellarg($passphrase ?? '')
        );

        shell_exec($certCmd);
        shell_exec($keyCmd);

        if (!file_exists($tempCert) || !file_exists($tempKey) || filesize($tempCert) === 0 || filesize($tempKey) === 0) {
            @unlink($tempCert);
            @unlink($tempKey);
            return null;
        }

        return ['cert' => $tempCert, 'key' => $tempKey];
    }

    private function normalizeStatus(string $gatewayStatus): string
    {
        $statusMap = [
            'ATIVA' => 'pending',
            'CONCLUIDA' => 'completed',
            'REMOVIDA_PELO_USUARIO_RECEBEDOR' => 'cancelled',
            'REMOVIDA_PELO_PSP' => 'cancelled',
        ];

        return $statusMap[strtoupper($gatewayStatus)] ?? 'pending';
    }

    private function getClientId(): string
    {
        return (string) ($this->mode === 'production'
            ? ($this->productionConfig['client_id'] ?? '')
            : ($this->sandboxConfig['client_id'] ?? ''));
    }

    private function getClientSecret(): string
    {
        return (string) ($this->mode === 'production'
            ? ($this->productionConfig['client_secret'] ?? '')
            : ($this->sandboxConfig['client_secret'] ?? ''));
    }

    private function getPixKey(): string
    {
        $key = (string) ($this->mode === 'production'
            ? ($this->productionConfig['pix_key'] ?? '')
            : ($this->sandboxConfig['pix_key'] ?? ''));

        if ($key === '') {
            throw new \RuntimeException('Sicoob PIX key is not configured.');
        }

        return $key;
    }

    private function getCertificatePfx(): ?string
    {
        return $this->mode === 'production'
            ? ($this->productionConfig['certificate_pfx'] ?? null)
            : ($this->sandboxConfig['certificate_pfx'] ?? null);
    }

    private function getCertificatePassphrase(): ?string
    {
        return $this->mode === 'production'
            ? ($this->productionConfig['certificate_passphrase'] ?? null)
            : ($this->sandboxConfig['certificate_passphrase'] ?? null);
    }
}
