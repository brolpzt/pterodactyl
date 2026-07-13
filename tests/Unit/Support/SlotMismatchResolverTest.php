<?php

namespace Pterodactyl\Tests\Unit\Support;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;
use Pterodactyl\Support\SlotMismatchResolver;
use Pterodactyl\Tests\TestCase;

class SlotMismatchResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testPickSlotVariablePrefersViewableDuplicate(): void
    {
        $server = $this->makeServer(eggWarn: true, variables: [
            $this->makeVariable(id: 10, env: 'SLOTS', value: '8', viewable: false),
            $this->makeVariable(id: 20, env: 'SLOTS', value: '16', viewable: true),
        ]);

        $picked = SlotMismatchResolver::pickSlotVariable($server, 'SLOTS');

        $this->assertNotNull($picked);
        $this->assertSame(20, $picked->id);
        $this->assertSame(16, SlotMismatchResolver::resolveConfiguredSlots($server));
    }

    public function testMismatchWhenReportedSlotsExceedConfiguredSlots(): void
    {
        $server = $this->makeServer(eggWarn: true, variables: [
            $this->makeVariable(id: 1, env: 'SLOTS', value: '16'),
        ]);

        $result = SlotMismatchResolver::resolve($server, true, 30);

        $this->assertTrue($result['mismatch']);
        $this->assertTrue($result['show_warning']);
        $this->assertSame(16, $result['configured']);
        $this->assertSame(30, $result['reported']);
    }

    public function testNoMismatchWhenReportedSlotsAreEqualToConfiguredSlots(): void
    {
        $server = $this->makeServer(eggWarn: true, variables: [
            $this->makeVariable(id: 1, env: 'SLOTS', value: '16'),
        ]);

        $result = SlotMismatchResolver::resolve($server, true, 16);

        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testUsesDefaultValueWhenServerValueIsMissing(): void
    {
        $server = $this->makeServer(eggWarn: true, variables: [
            $this->makeVariable(id: 1, env: 'SLOTS', value: null, default: '16'),
        ]);

        $this->assertSame(16, SlotMismatchResolver::resolveConfiguredSlots($server));
    }

    public function testFallsBackToMaxClientsWhenSlotsIsMissing(): void
    {
        $server = $this->makeServer(eggWarn: true, variables: [
            $this->makeVariable(id: 1, env: 'MAX_CLIENTS', value: '12'),
        ]);

        $this->assertSame(12, SlotMismatchResolver::resolveConfiguredSlots($server));
    }

    public function testWarningDisabledWhenEggSettingIsOff(): void
    {
        $server = $this->makeServer(eggWarn: false, variables: [
            $this->makeVariable(id: 1, env: 'SLOTS', value: '16'),
        ]);

        $result = SlotMismatchResolver::resolve($server, true, 30);

        $this->assertTrue($result['mismatch']);
        $this->assertFalse($result['warn_enabled']);
        $this->assertFalse($result['show_warning']);
    }

    public function testServerOverrideDisablesWarning(): void
    {
        $server = $this->makeServer(eggWarn: true, serverWarn: false, variables: [
            $this->makeVariable(id: 1, env: 'SLOTS', value: '16'),
        ]);

        $result = SlotMismatchResolver::resolve($server, true, 30);

        $this->assertTrue($result['mismatch']);
        $this->assertFalse($result['warn_enabled']);
        $this->assertFalse($result['show_warning']);
    }

    /**
     * @param list<EggVariable> $variables
     */
    private function makeServer(bool $eggWarn, array $variables = [], ?bool $serverWarn = null): Server
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
        $server->setRelation('variables', collect($variables));

        return $server;
    }

    private function makeVariable(
        int $id,
        string $env,
        ?string $value,
        ?string $default = null,
        bool $viewable = true,
    ): EggVariable {
        $variable = Mockery::mock(EggVariable::class)->makePartial();
        $variable->id = $id;
        $variable->env_variable = $env;
        $variable->server_value = $value;
        $variable->default_value = $default ?? $value ?? '18';
        $variable->user_viewable = $viewable;

        return $variable;
    }
}
