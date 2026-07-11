import useSWR, { SWRConfiguration } from 'swr';
import http from '@/api/http';
import { mapInstalledItem, WorkshopInstalledItem } from '@/api/server/workshop/types';

export default (uuid: string, config?: SWRConfiguration) => {
    return useSWR<WorkshopInstalledItem[]>(
        ['server:workshop:installed', uuid],
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/workshop/installed`);

            return (data.data ?? []).map((entry: { attributes: Record<string, unknown> }) =>
                mapInstalledItem(entry.attributes)
            );
        },
        config
    );
};
