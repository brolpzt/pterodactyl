<?php

namespace Pterodactyl\Tests\Integration\Services\Gcore;

use Pterodactyl\Services\Gcore\GcoreAclRuleNoteService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class GcoreAclRuleNoteServiceTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private function rule(string $policy, array $proto, array $dports, array $sip = [], array $extra = []): array
    {
        return ['policy' => $policy, 'proto_list' => $proto, 'dport_list' => $dports, 'sport_list' => [], 'sip_list' => $sip] + $extra;
    }

    public function testDescriptionSurvivesPortSyncAndReorder(): void
    {
        $notes = new GcoreAclRuleNoteService();
        $notes->store(5, [
            $this->rule('allowlist', ['any'], [], ['187.94.104.229'], ['description' => 'Escritório']),
            $this->rule('udp-general', ['udp'], [27015, 27016], [], ['description' => 'Jogos UDP']),
            $this->rule('DROP', ['tcp'], [22], [], ['description' => 'Bloqueia SSH']),
            $this->rule('DROP', ['tcp'], [3306], [], ['description' => 'Bloqueia MySQL']),
            $this->rule('allowlist', ['any'], [], [], ['ip_lists' => [3], 'description' => 'Cloudflare']),
        ]);

        // Gcore side after a port sync (udp-general got other ports) and a reorder of the DROPs.
        $result = $notes->annotate(5, [
            $this->rule('DROP', ['tcp'], [3306]),
            $this->rule('allowlist', ['any'], [], [], ['ip_lists' => [3]]),
            $this->rule('allowlist', ['any'], [], ['187.94.104.229']),
            $this->rule('udp-general', ['udp'], [27015, 27020, 27021]),
            $this->rule('DROP', ['tcp'], [22]),
            $this->rule('tcp-server', ['tcp'], [8080]),
        ]);

        $this->assertSame(
            ['Bloqueia MySQL', 'Cloudflare', 'Escritório', 'Jogos UDP', 'Bloqueia SSH', null],
            array_map(fn ($r) => $r['description'] ?? null, $result['acl'])
        );
        $this->assertSame(0, $result['lost']);
    }

    public function testRuleWhoseSourceChangedLosesItsDescription(): void
    {
        $notes = new GcoreAclRuleNoteService();
        $notes->store(5, [$this->rule('allowlist', ['any'], [], ['1.1.1.1'], ['description' => 'Antigo'])]);

        $result = $notes->annotate(5, [$this->rule('allowlist', ['any'], [], ['2.2.2.2'])]);

        $this->assertArrayNotHasKey('description', $result['acl'][0]);
        $this->assertSame(1, $result['lost']);
    }
}
