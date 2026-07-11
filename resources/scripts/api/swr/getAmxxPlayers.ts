import useSWR from 'swr';
import { ServerContext } from '@/state/server';
import getPlayers from '@/api/server/amxx/getPlayers';
import { AmxxPlayersResult } from '@/api/server/amxx/types';

    const REFRESH_INTERVAL_MS = 30000;

export default (enabled = true) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<AmxxPlayersResult>(
        enabled ? ['server:amxx:players', uuid] : null,
        () => getPlayers(uuid),
        {
            refreshInterval: REFRESH_INTERVAL_MS,
            revalidateOnFocus: true,
            dedupingInterval: 10000,
            errorRetryCount: 0,
            shouldRetryOnError: false,
        }
    );
};
