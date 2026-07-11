import useSWR, { ConfigInterface, responseInterface } from 'swr';
import { ActivityLog, Transformers } from '@definitions/user';
import { AxiosError } from 'axios';
import http, { PaginatedResult, QueryBuilderParams, withQueryBuilderParams } from '@/api/http';
import { toPaginatedSet } from '@definitions/helpers';
import useFilteredObject from '@/plugins/useFilteredObject';
import { useServerSWRKey } from '@/plugins/useSWRKey';
import { ServerContext } from '@/state/server';

export type ActivityLogFilters = QueryBuilderParams<'ip' | 'event', 'timestamp'>;

type ActivityLogConfig = ConfigInterface<PaginatedResult<ActivityLog>, AxiosError> & {
    perPage?: number;
};

const useActivityLogs = (
    filters?: ActivityLogFilters,
    config?: ActivityLogConfig
): responseInterface<PaginatedResult<ActivityLog>, AxiosError> => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const key = useServerSWRKey(['activity', useFilteredObject(filters || {}), config?.perPage ?? null]);
    const { perPage, ...swrConfig } = config || {};

    return useSWR<PaginatedResult<ActivityLog>>(
        key,
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/activity`, {
                params: {
                    ...withQueryBuilderParams(filters),
                    include: ['actor'],
                    ...(perPage ? { per_page: perPage } : {}),
                },
            });

            return toPaginatedSet(data, Transformers.toActivityLog);
        },
        { revalidateOnMount: false, ...swrConfig }
    );
};

export { useActivityLogs };
