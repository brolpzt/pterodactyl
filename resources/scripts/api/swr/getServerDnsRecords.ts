import useSWR from 'swr';
import http from '@/api/http';

export interface DnsZone {
    id: number;
    domain: string;
}

export interface DnsSrvTemplate {
    service: string;
    protocol: string;
    priority: number;
    weight: number;
}

export interface DnsMeta {
    enabled: boolean;
    allowedTypes: string[];
    defaultType: string;
    maxRecords: number;
    zones: DnsZone[];
    canCreate: boolean;
    primaryIp: string | null;
    primaryPort: number | null;
    primaryAlias: string | null;
    srv: DnsSrvTemplate;
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
    srvPort: number | null;
    srvPriority: number | null;
    srvWeight: number | null;
    srvService: string | null;
    srvProtocol: string | null;
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
                    srvPort: raw.attributes.srv_port,
                    srvPriority: raw.attributes.srv_priority,
                    srvWeight: raw.attributes.srv_weight,
                    srvService: raw.attributes.srv_service,
                    srvProtocol: raw.attributes.srv_protocol,
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
                    primaryPort: data.meta.dns.primary_port,
                    primaryAlias: data.meta.dns.primary_alias,
                    srv: {
                        service: data.meta.dns.srv.service,
                        protocol: data.meta.dns.srv.protocol,
                        priority: data.meta.dns.srv.priority,
                        weight: data.meta.dns.srv.weight,
                    },
                },
            };
        },
        config
    );
};
