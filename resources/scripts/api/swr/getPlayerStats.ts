import useSWR from 'swr';
import { ServerContext } from '@/state/server';
import getPlayerStats, { PlayerStatsResult } from '@/api/server/playerStats/getPlayerStats';

const REFRESH_INTERVAL_MS = 60_000;

export default (enabled = true, days = 7) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<PlayerStatsResult>(
        enabled ? ['server:player-stats', uuid, days] : null,
        () => getPlayerStats(uuid, days),
        {
            refreshInterval: REFRESH_INTERVAL_MS,
            revalidateOnFocus: true,
            dedupingInterval: 10_000,
            errorRetryCount: 1,
        }
    );
};
