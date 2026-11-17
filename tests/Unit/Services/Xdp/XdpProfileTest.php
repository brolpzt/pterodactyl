<?php

namespace Pterodactyl\Tests\Unit\Services\Xdp;

use Pterodactyl\Models\Egg;
use Pterodactyl\Tests\TestCase;
use Pterodactyl\Services\Xdp\XdpProfile;

class XdpProfileTest extends TestCase
{
    private function egg(?string $gamedig, string $name = 'Egg'): Egg
    {
        return (new Egg())->forceFill(['gamedig' => $gamedig, 'name' => $name]);
    }

    public function testAutomaticProfileFollowsTheGame(): void
    {
        $this->assertSame('cs16', XdpProfile::auto($this->egg('cs16')));
        $this->assertSame('cs16', XdpProfile::auto($this->egg(null, 'Counter-Strike 1.6')));
        $this->assertSame('source', XdpProfile::auto($this->egg('ets2')));
        $this->assertSame('source', XdpProfile::auto($this->egg(null, 'American Truck Simulator Dedicated Server')));
        $this->assertSame('source', XdpProfile::auto($this->egg('rust')));
        $this->assertSame('pluto', XdpProfile::auto($this->egg('plutonium')));
        $this->assertSame('cod2', XdpProfile::auto($this->egg(null, 'Call of Duty 2')));
        $this->assertSame('ts3', XdpProfile::auto($this->egg('ts3')));
        $this->assertSame('ts3', XdpProfile::auto($this->egg(null), isTs3: true));
        $this->assertSame('generic', XdpProfile::auto($this->egg('samp')));
        $this->assertSame('generic', XdpProfile::auto($this->egg('cod4')));
        $this->assertSame('generic', XdpProfile::auto($this->egg(null, 'Some Bot')));
    }

    public function testSlotValueNormalization(): void
    {
        $this->assertNull(XdpProfile::normalizeSlotValue(''));
        $this->assertNull(XdpProfile::normalizeSlotValue('auto'));
        $this->assertSame('none', XdpProfile::normalizeSlotValue(' NONE '));
        $this->assertSame('source', XdpProfile::normalizeSlotValue('Source'));
        $this->assertFalse(XdpProfile::normalizeSlotValue('quake'));
    }
}
