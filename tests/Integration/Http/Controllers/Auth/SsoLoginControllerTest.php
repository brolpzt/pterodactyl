<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Auth;

use Pterodactyl\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Events\Auth\DirectLogin;
use Pterodactyl\Services\Auth\SsoLoginService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

class SsoLoginControllerTest extends HttpTestCase
{
    private const SECRET = 'test-sso-secret-key-for-integration-tests';

    public function setUp(): void
    {
        parent::setUp();

        config([
            'sso.enabled' => true,
            'sso.secret' => self::SECRET,
            'sso.token_ttl' => 60,
            'sso.user_identifier' => 'external_id',
            'sso.allow_server_external_id' => false,
            'sso.allowed_ips' => [],
        ]);

        Event::fake([Failed::class, DirectLogin::class]);
    }

    public function testUserCanSignInViaValidSsoLink(): void
    {
        $user = User::factory()->create(['external_id' => 'client-42']);
        $server = $this->createServerModel(['owner_id' => $user->id]);

        $response = $this->get($this->signedUrl($user->external_id, $server->uuidShort));

        $response->assertRedirect('/server/' . $server->uuidShort);
        $this->assertAuthenticatedAs($user);
        Event::assertDispatched(DirectLogin::class);
    }

    public function testUserCanSignInViaValidSsoLinkWithNumericUserId(): void
    {
        config(['sso.user_identifier' => 'id']);

        $user = User::factory()->create();
        $server = $this->createServerModel(['owner_id' => $user->id]);

        $response = $this->get($this->signedUrl((string) $user->id, $server->uuidShort));

        $response->assertRedirect('/server/' . $server->uuidShort);
        $this->assertAuthenticatedAs($user);
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $user = User::factory()->create(['external_id' => 'client-42']);
        $server = $this->createServerModel(['owner_id' => $user->id]);

        $url = $this->signedUrl($user->external_id, $server->uuidShort);
        $url = str_replace('signature=', 'signature=deadbeef', $url);

        $this->get($url)->assertForbidden();
        $this->assertGuest();
        Event::assertDispatched(Failed::class);
    }

    public function testExpiredLinkIsRejected(): void
    {
        $user = User::factory()->create(['external_id' => 'client-42']);
        $server = $this->createServerModel(['owner_id' => $user->id]);

        $expires = time() - 10;
        $nonce = bin2hex(random_bytes(16));
        $signature = SsoLoginService::sign($user->external_id, $server->uuidShort, $expires, $nonce, self::SECRET);

        $this->get('/auth/sso?' . http_build_query([
            'user' => $user->external_id,
            'server' => $server->uuidShort,
            'expires' => $expires,
            'nonce' => $nonce,
            'signature' => $signature,
        ]))->assertForbidden();

        $this->assertGuest();
    }

    public function testNonceCannotBeReused(): void
    {
        $user = User::factory()->create(['external_id' => 'client-42']);
        $server = $this->createServerModel(['owner_id' => $user->id]);
        $url = $this->signedUrl($user->external_id, $server->uuidShort);

        $this->get($url)->assertRedirect();
        $this->get($url)->assertForbidden();
    }

    public function testUserCannotAccessAnotherUsersServer(): void
    {
        $owner = User::factory()->create(['external_id' => 'owner']);
        $intruder = User::factory()->create(['external_id' => 'intruder']);
        $server = $this->createServerModel(['owner_id' => $owner->id]);

        $this->get($this->signedUrl($intruder->external_id, $server->uuidShort))
            ->assertForbidden();

        $this->assertGuest();
    }

    private function signedUrl(string $userExternalId, string $serverUuidShort): string
    {
        $expires = time() + 30;
        $nonce = bin2hex(random_bytes(16));
        $signature = SsoLoginService::sign($userExternalId, $serverUuidShort, $expires, $nonce, self::SECRET);

        return '/auth/sso?' . http_build_query([
            'user' => $userExternalId,
            'server' => $serverUuidShort,
            'expires' => $expires,
            'nonce' => $nonce,
            'signature' => $signature,
        ]);
    }
}
