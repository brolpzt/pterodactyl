import http from '@/api/http';
import type { PeriodValue } from '@/components/elements/PeriodSelector';

export interface PlayerStatsSample {
    sampled_at: string;
    players: number;
    max_players: number;
    online: boolean;
}

export interface PlayerStatsSummary {
    current: number | null;
    peak: number | null;
    average: number | null;
    busiest_hour: {
        hour: number;
        average_players: number;
    } | null;
}

export interface PlayerStatsResult {
    supported: boolean;
    period: PeriodValue;
    samples: PlayerStatsSample[];
    summary: PlayerStatsSummary;
}

export default async (uuid: string, period: PeriodValue = '7d'): Promise<PlayerStatsResult> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/player-stats`, {
        params: { period },
    });

    return data.attributes as PlayerStatsResult;
};
