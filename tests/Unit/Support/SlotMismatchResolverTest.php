<?php

namespace Pterodactyl\Tests\Unit\Support;

use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function testSkipsHiddenSlotsAndFallsBackToViewableMaxClients(): void
    {
        $maxClients = $this->makeVariable(id: 30, env: 'MAX_CLIENTS', value: '16');
        $server = $this->makeServer(eggWarn: true);

        $server->shouldReceive('variables')->andReturn(
            $this->makeVariableQueryMock(null),
            $this->makeVariableQueryMock($maxClients),
        );

        $this->assertSame(16, SlotMismatchResolver::resolveConfiguredSlots($server));
    }

    public function testMismatchWhenReportedSlotsExceedConfiguredSlots(): void
    {
        $slots = $this->makeVariable(id: 1, env: 'SLOTS', value: '16');
        $server = $this->makeServer(eggWarn: true);
        $server->shouldReceive('variables')->once()->andReturn($this->makeVariableQueryMock($slots));

        $result = SlotMismatchResolver::resolve($server, true, 30);

        $this->assertTrue($result['mismatch']);
        $this->assertTrue($result['show_warning']);
        $this->assertSame(16, $result['configured']);
        $this->assertSame(30, $result['reported']);
    }

    public function testUsesDefaultValueWhenServerValueIsMissing(): void
    {
        $slots = $this->makeVariable(id: 1, env: 'SLOTS', value: null, default: '16');
        $server = $this->makeServer(eggWarn: true);
        $server->shouldReceive('variables')->once()->andReturn($this->makeVariableQueryMock($slots));

        $this->assertSame(16, SlotMismatchResolver::resolveConfiguredSlots($server));
    }

    public function testWarningDisabledWhenEggSettingIsOff(): void
    {
        $slots = $this->makeVariable(id: 1, env: 'SLOTS', value: '16');
        $server = $this->makeServer(eggWarn: false);
        $server->shouldReceive('variables')->once()->andReturn($this->makeVariableQueryMock($slots));

        $result = SlotMismatchResolver::resolve($server, true, 30);

        $this->assertTrue($result['mismatch']);
        $this->assertFalse($result['warn_enabled']);
        $this->assertFalse($result['show_warning']);
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
        $server->shouldReceive('relationLoaded')->with('variables')->andReturn(false);
        $server->setRelation('egg', $egg);

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

    private function makeVariableQueryMock(?EggVariable $result): HasMany
    {
        $query = Mockery::mock(HasMany::class);
        $query->shouldReceive('where')->with('egg_variables.env_variable', Mockery::any())->andReturnSelf();
        $query->shouldReceive('where')->with('user_viewable', true)->andReturnSelf();
        $query->shouldReceive('orderByDesc')->with('egg_variables.id')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($result);

        return $query;
    }
}
