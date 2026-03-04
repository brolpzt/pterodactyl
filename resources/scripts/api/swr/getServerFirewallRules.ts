import useSWR from 'swr';
import http from '@/api/http';

export interface FirewallRule {
    id: number;
    ip: string;
    reason: string | null;
    createdAt: string;
    updatedAt: string;
}

export default (uuid: string, config?: any) => {
    return useSWR<FirewallRule[]>(
        ['server:firewall', uuid],
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/firewall`);
            return data.data.map((raw: any) => ({
                id: raw.attributes.id,
                ip: raw.attributes.ip,
                reason: raw.attributes.reason,
                createdAt: raw.attributes.created_at,
                updatedAt: raw.attributes.updated_at,
            }));
        },
        config
    );
};
