import useSWR from 'swr';
import { ServerContext } from '@/state/server';
import getPlayerStats, { PlayerStatsResult } from '@/api/server/playerStats/getPlayerStats';
import type { PeriodValue } from '@/components/elements/PeriodSelector';

const REFRESH_INTERVAL_MS = 60_000;

export default (enabled = true, period: PeriodValue = '7d') => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<PlayerStatsResult>(
        enabled ? ['server:player-stats', uuid, period] : null,
        () => getPlayerStats(uuid, period),
        {
            refreshInterval: REFRESH_INTERVAL_MS,
            revalidateOnFocus: true,
            dedupingInterval: 10_000,
            errorRetryCount: 1,
        }
    );
};
