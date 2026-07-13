<?php

namespace Pterodactyl\Tests\Unit\Support;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\EnvironmentService;
use Pterodactyl\Support\SlotMismatchResolver;
use Pterodactyl\Tests\TestCase;

class SlotMismatchResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testParseSlotsFromEnvironmentUsesSlotsValue(): void
    {
        $this->assertSame(16, SlotMismatchResolver::parseSlotsFromEnvironment([
            'SLOTS' => '16',
        ]));
    }

    public function testParseSlotsFromEnvironmentFallsBackToMaxClients(): void
    {
        $this->assertSame(12, SlotMismatchResolver::parseSlotsFromEnvironment([
            'MAX_CLIENTS' => '12',
        ]));
    }

    public function testParseSlotsFromEnvironmentPrefersSlotsOverMaxClients(): void
    {
        $this->assertSame(16, SlotMismatchResolver::parseSlotsFromEnvironment([
            'SLOTS' => '16',
            'MAX_CLIENTS' => '8',
        ]));
    }

    public function testMismatchWhenReportedSlotsExceedConfiguredSlots(): void
    {
        $server = $this->makeServer(eggWarn: true);
        $this->bindEnvironment(['SLOTS' => '16']);

        $result = SlotMismatchResolver::resolve($server, true, 30);

        $this->assertTrue($result['mismatch']);
        $this->assertTrue($result['show_warning']);
        $this->assertSame(16, $result['configured']);
        $this->assertSame(30, $result['reported']);
    }

    public function testNoMismatchWhenReportedSlotsAreEqualToConfiguredSlots(): void
    {
        $server = $this->makeServer(eggWarn: true);
        $this->bindEnvironment(['SLOTS' => '16']);

        $result = SlotMismatchResolver::resolve($server, true, 16);

        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testNoMismatchWhenReportedSlotsAreBelowConfiguredSlots(): void
    {
        $server = $this->makeServer(eggWarn: true);
        $this->bindEnvironment(['SLOTS' => '16']);

        $result = SlotMismatchResolver::resolve($server, true, 12);

        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testWarningDisabledWhenEggSettingIsOff(): void
    {
        $server = $this->makeServer(eggWarn: false);
        $this->bindEnvironment(['SLOTS' => '16']);

        $result = SlotMismatchResolver::resolve($server, true, 30);

        $this->assertTrue($result['mismatch']);
        $this->assertFalse($result['warn_enabled']);
        $this->assertFalse($result['show_warning']);
    }

    public function testNoWarningWhenServerIsOffline(): void
    {
        $server = $this->makeServer(eggWarn: true);
        $this->bindEnvironment(['SLOTS' => '16']);

        $result = SlotMismatchResolver::resolve($server, false, 30);

        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testServerOverrideDisablesWarning(): void
    {
        $server = $this->makeServer(eggWarn: true, serverWarn: false);
        $this->bindEnvironment(['SLOTS' => '16']);

        $result = SlotMismatchResolver::resolve($server, true, 30);

        $this->assertTrue($result['mismatch']);
        $this->assertFalse($result['warn_enabled']);
        $this->assertFalse($result['show_warning']);
    }

    /**
     * @param array<string, string> $environment
     */
    private function bindEnvironment(array $environment): void
    {
        $service = Mockery::mock(EnvironmentService::class);
        $service->shouldReceive('handle')->andReturn($environment);
        $this->app->instance(EnvironmentService::class, $service);
    }

    private function makeServer(bool $eggWarn, ?bool $serverWarn = null): Server
    {
        $egg = Mockery::mock(Egg::class)->makePartial();
        $egg->warn_slot_mismatch = $eggWarn;

        $server = Mockery::mock(Server::class)->makePartial();
        $attributes = [];
        if ($serverWarn !== null) {
            $attributes['warn_slot_mismatch'] = $serverWarn ? 1 : 0;
        }
        $server->shouldReceive('getAttributes')->andReturn($attributes);
        $server->setRelation('egg', $egg);
        $server->setRelation('variables', collect());

        return $server;
    }
}
