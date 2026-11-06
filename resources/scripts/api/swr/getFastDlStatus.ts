import { useState } from 'react';
import useSWR from 'swr';
import { ServerContext } from '@/state/server';
import getFastDlStatus, { FastDlStatus } from '@/api/server/fastdl/getFastDlStatus';

const RUNNING_INTERVAL_MS = 2000;
const IDLE_INTERVAL_MS = 60000;

/** Polls quickly while a synchronization runs, slowly otherwise. */
export default (enabled = true) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [refreshInterval, setRefreshInterval] = useState(IDLE_INTERVAL_MS);

    return useSWR<FastDlStatus>(enabled ? ['server:fastdl', uuid] : null, () => getFastDlStatus(uuid), {
        refreshInterval,
        onSuccess: (data) =>
            setRefreshInterval(data.sync?.state === 'running' ? RUNNING_INTERVAL_MS : IDLE_INTERVAL_MS),
        revalidateOnFocus: true,
        dedupingInterval: 1000,
        errorRetryCount: 1,
    });
};
