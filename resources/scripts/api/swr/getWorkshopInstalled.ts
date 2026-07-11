import useSWR, { SWRConfiguration } from 'swr';
import http from '@/api/http';
import { mapInstalledItem, mapWorkshopSync, WorkshopInstalledResult } from '@/api/server/workshop/types';

export default (uuid: string, config?: SWRConfiguration) => {
    return useSWR<WorkshopInstalledResult>(
        ['server:workshop:installed', uuid],
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/workshop/installed`);

            return {
                items: (data.data ?? []).map((entry: { attributes: Record<string, unknown> }) =>
                    mapInstalledItem(entry.attributes)
                ),
                sync: mapWorkshopSync(data.meta?.sync as Record<string, unknown> | undefined),
            };
        },
        config
    );
};
