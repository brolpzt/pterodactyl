<?php

namespace Pterodactyl\Services\Firewall\Providers;

use RuntimeException;
use Pterodactyl\Models\Node;
use Pterodactyl\Contracts\Firewall\FirewallAccessProvider;

class NullFirewallAccessProvider implements FirewallAccessProvider
{
    public function key(): string
    {
        return 'none';
    }

    public function isConfigured(Node $node): bool
    {
        return false;
    }

    public function grant(Node $node, string $clientIp, int $port, ?string $notes = null): string
    {
        throw new RuntimeException('SFTP firewall access is not enabled on this node.');
    }

    public function revoke(Node $node, string $externalRuleId): void
    {
        // no-op
    }
}
