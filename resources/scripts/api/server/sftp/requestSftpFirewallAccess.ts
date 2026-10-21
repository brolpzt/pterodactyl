import http from '@/api/http';
import { rawToSftpFirewallAccess, SftpFirewallAccess } from '@/api/server/sftp/getSftpFirewallAccess';

export default (uuid: string, ip?: string): Promise<SftpFirewallAccess> => {
    return http
        .post(`/api/client/servers/${uuid}/sftp/firewall`, ip ? { ip } : {})
        .then(({ data }) => rawToSftpFirewallAccess(data.attributes));
};
