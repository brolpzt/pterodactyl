<?php

namespace Pterodactyl\Tests\Unit\Support;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;
use Pterodactyl\Support\HostnameBrandingResolver;
use Pterodactyl\Tests\TestCase;

class HostnameBrandingResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

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

    public function testWarnEnabledWhenBrandingEnvIsOne(): void
    {
        $branding = $this->makeVariable(value: '1');
        $server = $this->makeServer($branding);

        $this->assertTrue(HostnameBrandingResolver::resolveWarnEnabled($server));
    }

    public function testWarnDisabledWhenBrandingEnvIsZero(): void
    {
        $branding = $this->makeVariable(value: '0');
        $server = $this->makeServer($branding);

        $this->assertFalse(HostnameBrandingResolver::resolveWarnEnabled($server));
    }

    public function testWarnDisabledWhenEggHasNoBrandingVariable(): void
    {
        $server = $this->makeServer(null);

        $this->assertFalse(HostnameBrandingResolver::resolveWarnEnabled($server));
    }

    public function testShowWarningOnlyWhenBrandingEnabledAndHostnameMismatch(): void
    {
        $branding = $this->makeVariable(value: '1');
        $server = $this->makeServer($branding);

        $result = HostnameBrandingResolver::resolve($server, true, 'My Private Server');

        $this->assertTrue($result['mismatch']);
        $this->assertTrue($result['warn_enabled']);
        $this->assertTrue($result['show_warning']);
    }

    public function testNoWarningWhenBrandingDisabledEvenWithMismatch(): void
    {
        $branding = $this->makeVariable(value: '0');
        $server = $this->makeServer($branding);

        $result = HostnameBrandingResolver::resolve($server, true, 'My Private Server');

        $this->assertTrue($result['mismatch']);
        $this->assertFalse($result['warn_enabled']);
        $this->assertFalse($result['show_warning']);
    }

    public function testNoWarningForHostGamerDomainHostname(): void
    {
        $branding = $this->makeVariable(value: '1');
        $server = $this->makeServer($branding);

        $result = HostnameBrandingResolver::resolve(
            $server,
            true,
            "Ev'team~ TEAM // @HostGamer.com.br"
        );

        $this->assertTrue($result['compliant']);
        $this->assertFalse($result['mismatch']);
        $this->assertFalse($result['show_warning']);
    }

    public function testNoWarningWhenColorCodeSplitsHostGamer(): void
    {
        $branding = $this->makeVariable(value: '1');
        $server = $this->makeServer($branding);

        $result = HostnameBrandingResolver::resolve(
            $server,
            true,
            "Ev'team~ TEAM // @Host^Gamer.com.br"
        );

        $this->assertTrue($result['compliant']);
        $this->assertFalse($result['show_warning']);
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
            ["Ev'team~ TEAM // @HostGamer.com.br"],
            ["Ev'team~ TEAM // @Host^Gamer.com.br"],
            ['^7Ev\'team~ TEAM // @Host^Gamer.com.br'],
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

    private function makeServer(?EggVariable $branding): Server
    {
        $server = Mockery::mock(Server::class)->makePartial();
        $server->shouldReceive('relationLoaded')->with('variables')->andReturn(false);
        $server->shouldReceive('variables')->andReturn($this->makeVariableQueryMock($branding));

        return $server;
    }

    private function makeVariable(?string $value = null, ?string $default = '0'): EggVariable
    {
        $variable = new EggVariable();
        $variable->id = 1;
        $variable->env_variable = 'BRANDING';
        $variable->server_value = $value;
        $variable->default_value = $default;

        return $variable;
    }

    private function makeVariableQueryMock(?EggVariable $result): HasMany
    {
        $query = Mockery::mock(HasMany::class);
        $query->shouldReceive('where')->with('egg_variables.env_variable', 'BRANDING')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->with('egg_variables.id')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($result);

        return $query;
    }
}
