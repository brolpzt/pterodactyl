import http from '@/api/http';

export default (uuid: string, ip: string, reason?: string): Promise<void> => {
    return http
        .post(`/api/client/servers/${uuid}/firewall`, { ip, reason: reason ?? '' })
        .then(() => undefined);
};
