<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\PanelAlert;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PanelAlertsControllerTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        // The admin layout prints the request time; this is only defined by public/index.php.
        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));

        $this->admin = User::factory()->create(['root_admin' => true]);
        $this->withoutMiddleware(\Pterodactyl\Http\Middleware\VerifyCsrfToken::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'message' => 'Manutenção **hoje**',
            'type' => 'warning',
            'audience' => 'all',
            'enabled' => '1',
            'dismissible' => '0',
            'placements' => ['server', 'admin'],
            'server_pages' => ['/console'],
        ], $overrides);
    }

    public function testAlertCanBeCreatedEditedAndShownInAdminArea(): void
    {
        $egg = Egg::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/alerts/new', $this->payload(['egg_ids' => [(string) $egg->id], 'starts_at' => '2026-01-01T10:00']))
            ->assertRedirect();

        $alert = PanelAlert::query()->latest('id')->firstOrFail();
        $this->assertSame([$egg->id], $alert->egg_ids);
        $this->assertSame(['/console'], $alert->server_pages);
        $this->assertFalse($alert->dismissible);
        $this->assertSame('2026-01-01 10:00', $alert->starts_at->format('Y-m-d H:i'));

        $this->actingAs($this->admin)
            ->patch("/admin/alerts/view/{$alert->id}", $this->payload(['type' => 'danger', 'placements' => ['admin']]))
            ->assertRedirect();

        $alert->refresh();
        $this->assertSame('danger', $alert->type);
        $this->assertSame([], $alert->server_pages);
        $this->assertSame([], $alert->egg_ids);

        $this->actingAs($this->admin)->get('/admin/alerts')->assertOk();
        $this->actingAs($this->admin)->get('/admin/alerts/new')->assertOk();
        $this->actingAs($this->admin)
            ->get("/admin/alerts/view/{$alert->id}")
            ->assertOk()
            ->assertSee('alert alert-danger', false)
            ->assertSee('<strong>hoje</strong>', false);

        $this->actingAs($this->admin)->delete("/admin/alerts/view/{$alert->id}")->assertRedirect('/admin/alerts');
        $this->assertNull(PanelAlert::query()->find($alert->id));
    }

    public function testEndMustBeAfterStartOnlyWhenBothAreSet(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/alerts/new', $this->payload(['starts_at' => '2026-02-02T10:00', 'ends_at' => '2026-02-01T10:00']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.meta.source_field', 'ends_at');

        $this->actingAs($this->admin)
            ->post('/admin/alerts/new', $this->payload(['ends_at' => '2026-02-01T10:00']))
            ->assertRedirect();
    }

    public function testInvalidTypeIsRejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/alerts/new', $this->payload(['type' => 'purple']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.meta.source_field', 'type');
    }

    public function testNonAdminsAreRejected(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/alerts')->assertForbidden();
    }
}
