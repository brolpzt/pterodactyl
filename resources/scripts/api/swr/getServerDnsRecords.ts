import useSWR from 'swr';
import http from '@/api/http';

export interface DnsZone {
    id: number;
    domain: string;
}

export interface DnsMeta {
    enabled: boolean;
    allowedTypes: string[];
    defaultType: string;
    maxRecords: number;
    zones: DnsZone[];
    canCreate: boolean;
    primaryIp: string | null;
}

export interface DnsRecord {
    id: number;
    zoneId: number;
    domain: string | null;
    type: string;
    subdomain: string;
    name: string;
    content: string;
    ttl: number;
    proxied: boolean;
    createdAt: string;
    updatedAt: string;
}

export interface DnsData {
    records: DnsRecord[];
    meta: DnsMeta;
}

export default (uuid: string, config?: any) => {
    return useSWR<DnsData>(
        ['server:dns', uuid],
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/dns`);

            return {
                records: data.data.map((raw: any) => ({
                    id: raw.attributes.id,
                    zoneId: raw.attributes.zone_id,
                    domain: raw.attributes.domain,
                    type: raw.attributes.type,
                    subdomain: raw.attributes.subdomain,
                    name: raw.attributes.name,
                    content: raw.attributes.content,
                    ttl: raw.attributes.ttl,
                    proxied: raw.attributes.proxied,
                    createdAt: raw.attributes.created_at,
                    updatedAt: raw.attributes.updated_at,
                })),
                meta: {
                    enabled: data.meta.dns.enabled,
                    allowedTypes: data.meta.dns.allowed_types,
                    defaultType: data.meta.dns.default_type,
                    maxRecords: data.meta.dns.max_records,
                    zones: (data.meta.dns.zones || []).map((zone: any) => ({
                        id: zone.id,
                        domain: zone.domain,
                    })),
                    canCreate: data.meta.dns.can_create,
                    primaryIp: data.meta.dns.primary_ip,
                },
            };
        },
        config
    );
};
