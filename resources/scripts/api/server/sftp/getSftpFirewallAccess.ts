import http from '@/api/http';

export interface SftpFirewallAccess {
    enabled: boolean;
    clientIp: string | null;
    detectedIp: string | null;
    expiresAt: string | null;
    ttlMinutes: number;
    sftpHost: string;
    sftpPort: number;
}

export const rawToSftpFirewallAccess = (attributes: any): SftpFirewallAccess => ({
    enabled: !!attributes.enabled,
    clientIp: attributes.client_ip ?? null,
    detectedIp: attributes.detected_ip ?? null,
    expiresAt: attributes.expires_at ?? null,
    ttlMinutes: Number(attributes.ttl_minutes || 60),
    sftpHost: attributes.sftp_host || '',
    sftpPort: Number(attributes.sftp_port || 0),
});

export default (uuid: string): Promise<SftpFirewallAccess> => {
    return http
        .get(`/api/client/servers/${uuid}/sftp/firewall`)
        .then(({ data }) => rawToSftpFirewallAccess(data.attributes));
};
