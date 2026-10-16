<?php

namespace Pterodactyl\Tests\Unit\Models;

use Pterodactyl\Models\EggForcedCommand;
use Pterodactyl\Tests\TestCase;

class EggForcedCommandTest extends TestCase
{
    public function testBuildCommandReplacesValuePlaceholder(): void
    {
        $model = new EggForcedCommand([
            'env_variable' => 'TICKRATE',
            'command_template' => 'sys_ticrate {{VALUE}}',
        ]);

        $this->assertSame('sys_ticrate 1100', $model->buildCommand('1100'));
    }

    public function testBuildCommandReplacesEnvNamePlaceholder(): void
    {
        $model = new EggForcedCommand([
            'env_variable' => 'TICKRATE',
            'command_template' => 'sys_ticrate {{TICKRATE}}',
        ]);

        $this->assertSame('sys_ticrate 900', $model->buildCommand('900'));
    }

    public function testBuildCommandAppendsValueWhenNoPlaceholder(): void
    {
        $model = new EggForcedCommand([
            'env_variable' => 'TICKRATE',
            'command_template' => 'sys_ticrate',
        ]);

        $this->assertSame('sys_ticrate 1100', $model->buildCommand('1100'));
    }
}
