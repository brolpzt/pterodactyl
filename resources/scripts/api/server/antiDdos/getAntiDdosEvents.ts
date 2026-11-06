import http from '@/api/http';
import type { PeriodValue } from '@/components/elements/PeriodSelector';

export interface AntiDdosEndpoint {
    ip: string;
    port: number;
}

export interface AntiDdosEventSummary {
    id: string;
    alert_type: string;
    targeted_ip: string;
    ports: number[];
    attack_start_time: string | null;
    attack_end_time: string | null;
    attack_power_bps: number | null;
    attack_power_pps: number | null;
    share_percentage: number | null;
    source_ip_count: number | null;
    ongoing: boolean;
    has_detail: boolean;
    top_destination_ports: Array<{
        dst_port?: number;
        protocol?: string;
        bps?: number;
        pps?: number;
        bps_percentage?: number;
        pps_percentage?: number;
    }>;
}

export interface AntiDdosEventsResult {
    supported: boolean;
    ips: string[];
    endpoints: AntiDdosEndpoint[];
    period: PeriodValue;
    events: AntiDdosEventSummary[];
}

export interface AntiDdosEventDetail extends AntiDdosEventSummary {
    attack_traffic: Array<{
        dst_addr?: string;
        total_bytes?: number;
        total_packets?: number;
        interval_start_unix?: number;
    }>;
    attack_packet_sizes: Array<Record<string, unknown>>;
    attack_top_source_countries: Array<{
        source_country?: string;
        bps?: number;
        pps?: number;
    }>;
    attack_top_source_ips: Array<{
        src_addr?: string;
        bps?: number;
        pps?: number;
        bps_percentage?: number;
        pps_percentage?: number;
    }>;
    attack_top_source_ports: Array<Record<string, unknown>>;
    attack_top_destination_ports: AntiDdosEventSummary['top_destination_ports'];
    attack_top_protocols: Array<{
        protocol?: string;
        bps?: number;
        pps?: number;
        bps_percentage?: number;
        pps_percentage?: number;
    }>;
}

export const getAntiDdosEvents = async (
    uuid: string,
    period: PeriodValue = '7d'
): Promise<AntiDdosEventsResult> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/anti-ddos/events`, {
        params: { period },
    });

    return data.attributes as AntiDdosEventsResult;
};

export const getAntiDdosEvent = async (uuid: string, eventId: string): Promise<AntiDdosEventDetail> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/anti-ddos/events/${eventId}`);

    return data.attributes as AntiDdosEventDetail;
};
