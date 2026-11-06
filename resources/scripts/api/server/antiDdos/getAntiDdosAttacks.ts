import http from '@/api/http';

export interface AntiDdosAttackSummary {
    id: string;
    alert_type: string;
    targeted_ip: string;
    attack_start_time: string | null;
    attack_end_time: string | null;
    attack_power_bps: number | null;
    attack_power_pps: number | null;
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

export interface AntiDdosAttacksResult {
    supported: boolean;
    ips: string[];
    days: number;
    attacks: AntiDdosAttackSummary[];
}

export interface AntiDdosAttackDetail extends AntiDdosAttackSummary {
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
    attack_top_destination_ports: AntiDdosAttackSummary['top_destination_ports'];
    attack_top_protocols: Array<{
        protocol?: string;
        bps?: number;
        pps?: number;
        bps_percentage?: number;
        pps_percentage?: number;
    }>;
}

export const getAntiDdosAttacks = async (uuid: string, days = 30): Promise<AntiDdosAttacksResult> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/anti-ddos/attacks`, {
        params: { days },
    });

    return data.attributes as AntiDdosAttacksResult;
};

export const getAntiDdosAttack = async (uuid: string, attackId: string): Promise<AntiDdosAttackDetail> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/anti-ddos/attacks/${attackId}`);

    return data.attributes as AntiDdosAttackDetail;
};
