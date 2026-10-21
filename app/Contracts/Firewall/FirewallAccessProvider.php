<?php

namespace Pterodactyl\Contracts\Firewall;

use Pterodactyl\Models\Node;

interface FirewallAccessProvider
{
    /**
     * Unique provider key (e.g. "vultr").
     */
    public function key(): string;

    /**
     * Whether this node is configured enough to grant access.
     */
    public function isConfigured(Node $node): bool;

    /**
     * Create a temporary allow rule for the client IP.
     *
     * @return string External rule identifier used later for revoke.
     */
    public function grant(Node $node, string $clientIp, int $port, ?string $notes = null): string;

    /**
     * Remove a previously granted rule.
     */
    public function revoke(Node $node, string $externalRuleId): void;
}
