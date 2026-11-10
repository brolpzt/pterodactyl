<?php

namespace Pterodactyl\Tests\Unit\Services\Gcore;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Services\Gcore\IpLists\IpListParser;
use Pterodactyl\Services\Gcore\IpLists\GcoreIpListBindingService;

class IpListParserTest extends TestCase
{
    public function testPlainTextWithTrailingSpacesCommentsAndNoFinalNewline(): void
    {
        // Akamai-style: trailing spaces, last line without newline.
        $r = (new IpListParser(8))->parse("2.16.0.0/13  \n# comment\n23.0.0.0/12  ; note\r\n184.24.0.0/13");

        $this->assertSame(['2.16.0.0/13', '23.0.0.0/12', '184.24.0.0/13'], $r['cidrs']);
        $this->assertSame(0, $r['invalid']);
    }

    public function testJsonDropsIpv6AndIgnoresMetadataStrings(): void
    {
        $json = json_encode([
            'syncToken' => '1794297916545',
            'creationTime' => '2026-11-10T00:05:16.545143',
            'prefixes' => [
                ['ipv4Prefix' => '8.8.8.0/24'],
                ['ipv6Prefix' => '2001:4860::/32'],
                ['ipv4Prefix' => '8.8.4.0/24'],
            ],
        ]);

        $r = (new IpListParser(8))->parse($json);

        $this->assertSame(['8.8.4.0/24', '8.8.8.0/24'], $r['cidrs']);
        $this->assertSame(1, $r['ipv6']);
        $this->assertSame(0, $r['invalid']);
    }

    public function testRangesNormalisationAggregationAndSafetyFilters(): void
    {
        $r = (new IpListParser(8))->parse("10.0.0.5/24\n10.0.1.0/24\n1.2.3.4-1.2.3.7\n0.0.0.0/0\n::1\nnot-an-ip\n9.9.9.9/32");

        // 10.0.0.5/24 → 10.0.0.0/24, merged with 10.0.1.0/24; /32 written bare.
        $this->assertSame(['1.2.3.4/30', '9.9.9.9', '10.0.0.0/23'], $r['cidrs']);
        $this->assertSame(1, $r['ipv6']);
        $this->assertSame(1, $r['invalid']);
        $this->assertSame(1, $r['too_broad']);
    }

    public function testAggregateUnionOfLists(): void
    {
        $this->assertSame(
            ['104.16.0.0/12'],
            IpListParser::aggregate(['104.16.0.0/13', '104.24.0.0/14', '104.28.0.0/14', '104.24.0.0/14'])
        );
    }

    public function testSignatureIgnoresOrder(): void
    {
        $a = GcoreIpListBindingService::signature(['policy' => 'allowlist', 'proto_list' => ['udp', 'tcp'], 'dport_list' => [27016, 27015], 'sport_list' => []]);
        $b = GcoreIpListBindingService::signature(['policy' => 'allowlist', 'proto_list' => ['tcp', 'udp'], 'dport_list' => [27015, 27016]]);

        $this->assertSame($a, $b);
    }
}
