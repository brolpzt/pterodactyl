<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Api\HgHlds;

use Pterodactyl\Models\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Services\HgHlds\ActivationService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class ActivationControllerTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private const NODE_IP = '89.39.161.189';
    private const CLOUDFLARE_IP = '172.67.136.252';
    private const OTHER_IP = '203.0.113.9';

    private SettingsRepositoryInterface $settings;

    private string $keypair;

    private string $nonce;

    public function setUp(): void
    {
        parent::setUp();

        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));

        $this->settings = $this->app->make(SettingsRepositoryInterface::class);
        $this->keypair = sodium_crypto_sign_keypair();
        $this->nonce = str_repeat('ab12', 16);

        $this->settings->set(ActivationService::SETTING_ALLOWLIST, self::NODE_IP . "\n198.51.100.0/24");
        $this->settings->set(
            ActivationService::SETTING_PRIVATE_KEY,
            $this->app->make(Encrypter::class)->encrypt(bin2hex(sodium_crypto_sign_secretkey($this->keypair)))
        );
    }

    protected function tearDown(): void
    {
        $this->settings->forget(ActivationService::SETTING_ALLOWLIST);
        $this->settings->forget(ActivationService::SETTING_PRIVATE_KEY);

        parent::tearDown();
    }

    private function activate(string $remote, array $headers = [], ?string $body = null)
    {
        return $this->call('POST', '/api/hghlds/activate', [], [], [], array_merge([
            'REMOTE_ADDR' => $remote,
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ], $headers), $body ?? "nonce={$this->nonce}");
    }

    public function testAllowedNodeReceivesASignedLicense(): void
    {
        $response = $this->activate(self::NODE_IP);

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));

        [$payload, $signature] = explode('.', $response->getContent());

        $this->assertTrue(sodium_crypto_sign_verify_detached(
            base64_decode($signature),
            $payload,
            sodium_crypto_sign_publickey($this->keypair)
        ));
        $this->assertSame(
            sprintf('nonce=%s;exp=%d;feat=3', $this->nonce, now()->addDays(3)->getTimestamp()),
            base64_decode($payload)
        );
    }

    public function testAddressesOutsideTheAllowlistAreRejectedWithAnEmptyBody(): void
    {
        $response = $this->activate(self::OTHER_IP);

        $response->assertForbidden();
        $this->assertSame('', $response->getContent());
    }

    public function testCidrRangesAreAccepted(): void
    {
        $this->activate('198.51.100.77')->assertOk();
    }

    /**
     * @dataProvider spoofedHeaderProvider
     */
    public function testHeadersCannotSpoofADirectConnection(string $header): void
    {
        $this->activate(self::OTHER_IP, [$header => self::NODE_IP])->assertForbidden();
    }

    public static function spoofedHeaderProvider(): array
    {
        return [['HTTP_CF_CONNECTING_IP'], ['HTTP_X_FORWARDED_FOR'], ['HTTP_X_REAL_IP']];
    }

    public function testCloudflareRequestsUseTheConnectingIpHeader(): void
    {
        $this->activate(self::CLOUDFLARE_IP, ['HTTP_CF_CONNECTING_IP' => self::NODE_IP])->assertOk();
        $this->activate(self::CLOUDFLARE_IP, ['HTTP_CF_CONNECTING_IP' => self::OTHER_IP])->assertForbidden();
        $this->activate(self::CLOUDFLARE_IP, ['HTTP_X_FORWARDED_FOR' => self::NODE_IP])->assertForbidden();
        $this->activate(self::CLOUDFLARE_IP, ['HTTP_CF_CONNECTING_IP' => 'lixo'])->assertForbidden();
    }

    /**
     * @dataProvider invalidBodyProvider
     */
    public function testInvalidNoncesAreRejected(string $body): void
    {
        $response = $this->activate(self::NODE_IP, [], $body);

        $response->assertStatus(400);
        $this->assertSame('', $response->getContent());
    }

    public static function invalidBodyProvider(): array
    {
        return [
            'short' => ['nonce=abcd'],
            'uppercase' => ['nonce=' . str_repeat('AB12', 16)],
            'extra field' => ['nonce=' . str_repeat('ab12', 16) . ';feat=7'],
            'prefixed' => ['x=1&nonce=' . str_repeat('ab12', 16)],
            'empty' => ['nonce='],
        ];
    }

    public function testMissingKeyIsReportedOnlyToAllowedNodes(): void
    {
        $this->settings->forget(ActivationService::SETTING_PRIVATE_KEY);

        $this->activate(self::NODE_IP)->assertStatus(503);
        $this->activate(self::OTHER_IP)->assertForbidden();
    }

    public function testSecretKeyFormats(): void
    {
        $secret = sodium_crypto_sign_secretkey($this->keypair);

        $this->assertSame($secret, ActivationService::parseSecretKey(bin2hex($secret)));
        $this->assertSame($secret, ActivationService::parseSecretKey(bin2hex(substr($secret, 0, 32))));
        $this->assertNull(ActivationService::parseSecretKey(bin2hex(substr($secret, 0, 32) . str_repeat("\0", 32))));
        $this->assertNull(ActivationService::parseSecretKey('abc'));
    }

    public function testAdminCanConfigureActivationWithoutExposingTheKey(): void
    {
        $admin = User::factory()->create(['root_admin' => true]);
        $this->withoutMiddleware(\Pterodactyl\Http\Middleware\VerifyCsrfToken::class);

        $keypair = sodium_crypto_sign_keypair();
        $secretHex = bin2hex(sodium_crypto_sign_secretkey($keypair));

        $this->actingAs($admin)
            ->post('/admin/hghlds', ['allowlist' => " 10.0.0.1\r\n\r\n10.0.1.0/24, 10.0.0.1 ", 'private_key' => $secretHex])
            ->assertRedirect('/admin/hghlds');

        $this->assertSame("10.0.0.1\n10.0.1.0/24", $this->settings->get(ActivationService::SETTING_ALLOWLIST, ''));
        $this->assertStringNotContainsString($secretHex, $this->settings->get(ActivationService::SETTING_PRIVATE_KEY, ''));

        $this->actingAs($admin)
            ->get('/admin/hghlds')
            ->assertOk()
            ->assertSee(bin2hex(sodium_crypto_sign_publickey($keypair)))
            ->assertDontSee($secretHex);

        // Leaving the field blank keeps the current key.
        $this->actingAs($admin)->post('/admin/hghlds', ['allowlist' => '10.0.0.2'])->assertRedirect();
        $this->actingAs($admin)->get('/admin/hghlds')->assertSee(bin2hex(sodium_crypto_sign_publickey($keypair)));

        $this->actingAs($admin)->post('/admin/hghlds', ['allowlist' => '10.0.0.999'])->assertUnprocessable();
        $this->actingAs($admin)->post('/admin/hghlds', ['private_key' => 'abc'])->assertUnprocessable();
    }

    public function testNonAdminsCannotOpenTheSettings(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/hghlds')->assertForbidden();
    }
}
