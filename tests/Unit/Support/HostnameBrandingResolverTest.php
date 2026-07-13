<?php

namespace Pterodactyl\Tests\Unit\Support;

use Pterodactyl\Support\HostnameBrandingResolver;
use PHPUnit\Framework\TestCase;

class HostnameBrandingResolverTest extends TestCase
{
    /**
     * @dataProvider compliantHostnameProvider
     */
    public function testHostnameIsCompliant(string $hostname): void
    {
        $this->assertTrue(HostnameBrandingResolver::hostnameContainsBranding($hostname));
    }

    /**
     * @dataProvider nonCompliantHostnameProvider
     */
    public function testHostnameIsNotCompliant(string $hostname): void
    {
        $this->assertFalse(HostnameBrandingResolver::hostnameContainsBranding($hostname));
    }

    public static function compliantHostnameProvider(): array
    {
        return [
            ['@HostGamer | BO2'],
            ['HOST GAMER #1'],
            ['^2[HOSTGAMER] ^7Fun Server'],
            ['@HOSTGAMER.COM.BR'],
            ['host-gamer clan server'],
            ['HOST_GAMER_ARENA'],
            ['servidor host gamer br'],
        ];
    }

    public static function nonCompliantHostnameProvider(): array
    {
        return [
            ['My Private Server'],
            ['Clan XYZ'],
            [''],
            ['hostile gamer squad'],
            ['gamer only'],
            ['host server'],
        ];
    }

    public function testStripHostnameColorsRemovesGoldSrcCodes(): void
    {
        $this->assertSame(
            '[HOSTGAMER] Fun',
            HostnameBrandingResolver::stripHostnameColors('^2[HOSTGAMER] ^7Fun')
        );
    }
}
