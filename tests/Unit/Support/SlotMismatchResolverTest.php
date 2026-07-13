<?php

namespace Pterodactyl\Tests\Unit\Support;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;
use Pterodactyl\Support\SlotMismatchResolver;

class SlotMismatchResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    public function testMismatchWhenReportedSlotsExceedConfiguredLimit(): void
    {
        $server = $this->makeServerWithVariables([
            SlotMismatchResolver::WARN_ENV => '1',
            SlotMismatchResolver::SLOTS_ENV_VARIABLE => 'MAX_CLIENTS',
            'MAX_CLIENTS' => '18',
        ]);

        $result = SlotMismatchResolver::resolve($server, true, 32);

        $this->assertTrue($result['mismatch']);
        $this->assertTrue($result['show_warning']);
        $this->assertSame(18, $result['configured']);
        $this->assertSame(32, $result['reported']);
        $this->assertSame('MAX_CLIENTS', $result['env_variable']);
    }

    public function testNoMismatchWhenReportedSlotsAreEqualToConfiguredLimit(): void
    {
        $server = $this->makeServerWithVariables([
            SlotMismatchResolver::WARN_ENV => '1',
            SlotMismatchResolver::SLOTS_ENV_VARIABLE => 'MAX_CLIENTS',
            'MAX_CLIENTS' => '18',
        ]);

        $result = SlotMismatchResolver::resolve($server, true, 18);

        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testNoMismatchWhenReportedSlotsAreBelowConfiguredLimit(): void
    {
        $server = $this->makeServerWithVariables([
            SlotMismatchResolver::WARN_ENV => '1',
            SlotMismatchResolver::SLOTS_ENV_VARIABLE => 'MAX_CLIENTS',
            'MAX_CLIENTS' => '18',
        ]);

        $result = SlotMismatchResolver::resolve($server, true, 12);

        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testWarningDisabledWhenAdminTurnsOffWarnSlotMismatch(): void
    {
        $server = $this->makeServerWithVariables([
            SlotMismatchResolver::WARN_ENV => '0',
            SlotMismatchResolver::SLOTS_ENV_VARIABLE => 'MAX_CLIENTS',
            'MAX_CLIENTS' => '18',
        ]);

        $result = SlotMismatchResolver::resolve($server, true, 32);

        $this->assertTrue($result['mismatch']);
        $this->assertFalse($result['warn_enabled']);
        $this->assertFalse($result['show_warning']);
    }

    public function testNoWarningWhenServerIsOffline(): void
    {
        $server = $this->makeServerWithVariables([
            SlotMismatchResolver::WARN_ENV => '1',
            SlotMismatchResolver::SLOTS_ENV_VARIABLE => 'MAX_CLIENTS',
            'MAX_CLIENTS' => '18',
        ]);

        $result = SlotMismatchResolver::resolve($server, false, 32);

        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testServerOverrideForWarnSlotMismatch(): void
    {
        $server = $this->makeServerWithVariables([
            SlotMismatchResolver::WARN_ENV => ['default' => '1', 'server' => '0'],
            SlotMismatchResolver::SLOTS_ENV_VARIABLE => 'MAX_CLIENTS',
            'MAX_CLIENTS' => '18',
        ]);

        $result = SlotMismatchResolver::resolve($server, true, 32);

        $this->assertTrue($result['mismatch']);
        $this->assertFalse($result['warn_enabled']);
        $this->assertFalse($result['show_warning']);
    }

    /**
     * @param array<string, string|array{default: string, server?: string}> $variables
     */
    private function makeServerWithVariables(array $variables): Server
    {
        $eggVariables = collect();

        foreach ($variables as $env => $config) {
            $default = is_array($config) ? $config['default'] : $config;
            $serverValue = is_array($config) ? ($config['server'] ?? null) : null;

            $variable = Mockery::mock(EggVariable::class)->makePartial();
            $variable->env_variable = $env;
            $variable->default_value = $default;
            $variable->server_value = $serverValue;

            $eggVariables->push($variable);
        }

        $server = Mockery::mock(Server::class)->makePartial();
        $server->setRelation('variables', $eggVariables);

        return $server;
    }
}
