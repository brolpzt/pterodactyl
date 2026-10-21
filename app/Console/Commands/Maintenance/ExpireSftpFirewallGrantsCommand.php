<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Pterodactyl\Services\Firewall\SftpFirewallGrantService;

class ExpireSftpFirewallGrantsCommand extends Command
{
    protected $signature = 'p:maintenance:expire-sftp-firewall-grants';

    protected $description = 'Revoke expired temporary SFTP firewall grants (e.g. Vultr rules).';

    public function handle(SftpFirewallGrantService $service): int
    {
        $stats = $service->expireDue();

        $this->line(sprintf(
            'SFTP firewall grants: revoked=%d failed=%d',
            $stats['revoked'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
