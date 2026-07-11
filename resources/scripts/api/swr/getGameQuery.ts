import useSWR from 'swr';
import { ServerContext } from '@/state/server';
import getGameQuery from '@/api/server/gameQuery/getGameQuery';
import { GameQueryResult } from '@/api/server/gameQuery/types';

const REFRESH_INTERVAL_MS = 60000;

export default (enabled = true) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<GameQueryResult>(
        enabled ? ['server:query', uuid] : null,
        () => getGameQuery(uuid),
        {
            refreshInterval: REFRESH_INTERVAL_MS,
            revalidateOnFocus: true,
            dedupingInterval: 5000,
            errorRetryCount: 1,
        }
    );
};
