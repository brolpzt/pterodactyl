import http from '@/api/http';

export default (
    uuid: string,
    data: {
        zoneId: number;
        subdomain: string;
        type: string;
        content?: string;
        proxied?: boolean;
    }
): Promise<void> => {
    return http
        .post(`/api/client/servers/${uuid}/dns`, {
            zone_id: data.zoneId,
            subdomain: data.subdomain,
            type: data.type,
            content: data.content || null,
            proxied: data.proxied ?? false,
        })
        .then(() => undefined);
};
