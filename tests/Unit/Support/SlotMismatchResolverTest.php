<?php

namespace Pterodactyl\Tests\Unit\Support;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;
use Pterodactyl\Support\SlotMismatchResolver;

class SlotMismatchResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testMismatchWhenReportedSlotsExceedConfiguredSlots(): void
    {
        $server = $this->makeServer(slots: '18', eggWarn: true);

        $result = SlotMismatchResolver::resolve($server, true, 32);

        $this->assertTrue($result['mismatch']);
        $this->assertTrue($result['show_warning']);
        $this->assertSame(18, $result['configured']);
        $this->assertSame(32, $result['reported']);
        $this->assertSame('SLOTS', $result['env_variable']);
    }

    public function testNoMismatchWhenReportedSlotsAreEqualToConfiguredSlots(): void
    {
        $server = $this->makeServer(slots: '18', eggWarn: true);

        $result = SlotMismatchResolver::resolve($server, true, 18);

        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testNoMismatchWhenReportedSlotsAreBelowConfiguredSlots(): void
    {
        $server = $this->makeServer(slots: '18', eggWarn: true);

        $result = SlotMismatchResolver::resolve($server, true, 12);

        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testWarningDisabledWhenEggSettingIsOff(): void
    {
        $server = $this->makeServer(slots: '18', eggWarn: false);

        $result = SlotMismatchResolver::resolve($server, true, 32);

        $this->assertTrue($result['mismatch']);
        $this->assertFalse($result['warn_enabled']);
        $this->assertFalse($result['show_warning']);
    }

    public function testNoWarningWhenServerIsOffline(): void
    {
        $server = $this->makeServer(slots: '18', eggWarn: true);

        $result = SlotMismatchResolver::resolve($server, false, 32);

        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testServerOverrideDisablesWarning(): void
    {
        $server = $this->makeServer(slots: '18', eggWarn: true, serverWarn: false);

        $result = SlotMismatchResolver::resolve($server, true, 32);

        $this->assertTrue($result['mismatch']);
        $this->assertFalse($result['warn_enabled']);
        $this->assertFalse($result['show_warning']);
    }

    private function makeServer(string $slots, bool $eggWarn, ?bool $serverWarn = null): Server
    {
        $variable = Mockery::mock(EggVariable::class)->makePartial();
        $variable->env_variable = 'SLOTS';
        $variable->default_value = $slots;
        $variable->server_value = null;

        $egg = Mockery::mock(Egg::class)->makePartial();
        $egg->warn_slot_mismatch = $eggWarn;

        $server = Mockery::mock(Server::class)->makePartial();
        $attributes = [];
        if ($serverWarn !== null) {
            $attributes['warn_slot_mismatch'] = $serverWarn ? 1 : 0;
        }
        $server->shouldReceive('getAttributes')->andReturn($attributes);
        $server->setRelation('egg', $egg);
        $server->setRelation('variables', collect([$variable]));

        return $server;
    }
}
