import useSWR from 'swr';
import { ServerContext } from '@/state/server';
import getPlayers from '@/api/server/webrcon/getPlayers';
import { WebRconPlayersResult } from '@/api/server/webrcon/types';

export default (enabled = true) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<WebRconPlayersResult>(
        enabled ? ['server:webrcon:players', uuid] : null,
        () => getPlayers(uuid),
        { refreshInterval: 10000, revalidateOnFocus: true },
    );
};
