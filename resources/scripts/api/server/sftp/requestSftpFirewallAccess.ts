import http from '@/api/http';
import { rawToSftpFirewallAccess, SftpFirewallAccess } from '@/api/server/sftp/getSftpFirewallAccess';

export default (uuid: string): Promise<SftpFirewallAccess> => {
    return http
        .post(`/api/client/servers/${uuid}/sftp/firewall`)
        .then(({ data }) => rawToSftpFirewallAccess(data.attributes));
};
