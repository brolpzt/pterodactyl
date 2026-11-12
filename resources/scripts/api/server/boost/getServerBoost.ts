import http from '@/api/http';
import type { PeriodValue } from '@/components/elements/PeriodSelector';

export interface ServerBoostConnection {
    id: number;
    player: string | null;
    steam_id: string | null;
    connected_at: string;
}

export interface ServerBoostResult {
    period: PeriodValue;
    since: string;
    /** false while the server is hidden from the boost list, suspended or installing. */
    listed: boolean;
    total: number;
    players: number;
    last_connected_at: string | null;
    /** Connections per UTC hour; the page re-buckets them in the browser's time zone. */
    hourly: Array<{ hour: string; count: number }>;
    connections: ServerBoostConnection[];
}

export const getServerBoost = async (uuid: string, period: PeriodValue = '7d'): Promise<ServerBoostResult> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/boost`, { params: { period } });

    return data.attributes as ServerBoostResult;
};
