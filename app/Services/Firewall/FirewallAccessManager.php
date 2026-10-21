<?php

namespace Pterodactyl\Services\Firewall;

use RuntimeException;
use Pterodactyl\Models\Node;
use Pterodactyl\Contracts\Firewall\FirewallAccessProvider;
use Pterodactyl\Services\Firewall\Providers\NullFirewallAccessProvider;
use Pterodactyl\Services\Firewall\Providers\VultrFirewallAccessProvider;

class FirewallAccessManager
{
    /** @var array<string, FirewallAccessProvider> */
    private array $providers;

    public function __construct(
        NullFirewallAccessProvider $nullProvider,
        VultrFirewallAccessProvider $vultrProvider,
    ) {
        $this->providers = [
            $nullProvider->key() => $nullProvider,
            $vultrProvider->key() => $vultrProvider,
        ];
    }

    public function forNode(Node $node): FirewallAccessProvider
    {
        $key = $node->sftp_firewall_provider ?: 'none';

        return $this->providers[$key] ?? throw new RuntimeException("Unknown SFTP firewall provider: {$key}");
    }

    /**
     * @return list<string>
     */
    public function availableProviders(): array
    {
        return array_keys($this->providers);
    }
}
