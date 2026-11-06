import http from '@/api/http';

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
    days: number;
    samples: PlayerStatsSample[];
    summary: PlayerStatsSummary;
}

export default async (uuid: string, days = 7): Promise<PlayerStatsResult> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/player-stats`, {
        params: { days },
    });

    return data.attributes as PlayerStatsResult;
};
