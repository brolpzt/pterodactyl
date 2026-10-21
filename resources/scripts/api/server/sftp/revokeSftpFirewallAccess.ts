import http from '@/api/http';
import { rawToSftpFirewallAccess, SftpFirewallAccess } from '@/api/server/sftp/getSftpFirewallAccess';

export default (uuid: string, ip?: string): Promise<SftpFirewallAccess> => {
    return http
        .delete(`/api/client/servers/${uuid}/sftp/firewall`, {
            params: ip ? { ip } : undefined,
        })
        .then(({ data }) => rawToSftpFirewallAccess(data.attributes));
};
