<?php

namespace Pterodactyl\Tests\Unit\Support;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Support\GameDigTypeResolver;

class GameDigTypeResolverTest extends TestCase
{
    public function testTruckSimulatorsResolve(): void
    {
        $this->assertSame('ets2', GameDigTypeResolver::resolve('ets2', 'Euro Truck Simulator 2 Dedicated server'));
        $this->assertSame('ets2', GameDigTypeResolver::resolve(null, 'Euro Truck Simulator 2 Dedicated server'));
        $this->assertSame('ats', GameDigTypeResolver::resolve(null, 'American Truck Simulator'));
        $this->assertSame('ats', GameDigTypeResolver::resolve('ats', null));
    }

    public function testOtherEggsAreUnchanged(): void
    {
        $this->assertSame('cs16', GameDigTypeResolver::resolve(null, 'Counter-Strike 1.6'));
        $this->assertSame('cod2', GameDigTypeResolver::resolve(null, 'Call of Duty 2'));
        $this->assertSame('plutonium', GameDigTypeResolver::resolve('t6', 'T6 - Black Ops 2'));
        $this->assertNull(GameDigTypeResolver::resolve(null, 'Discord Tickets Bot'));
        $this->assertNull(GameDigTypeResolver::resolve(null, 'Stats Bot'));
    }
}
