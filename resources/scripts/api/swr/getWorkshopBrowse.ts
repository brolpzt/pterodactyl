import useSWR, { SWRConfiguration } from 'swr';
import http from '@/api/http';
import { mapWorkshopItem, mapWorkshopSync, WorkshopBrowseResult } from '@/api/server/workshop/types';

export interface WorkshopBrowseParams {
    q?: string;
    sort?: 'trending' | 'popular' | 'recent';
    cursor?: string | null;
    perPage?: number;
}

export default (uuid: string, params: WorkshopBrowseParams, config?: SWRConfiguration) => {
    const { q = '', sort = 'trending', cursor = '*', perPage = 30 } = params;

    return useSWR<WorkshopBrowseResult>(
        ['server:workshop:browse', uuid, q, sort, cursor, perPage],
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/workshop/browse`, {
                params: {
                    q: q || undefined,
                    sort,
                    cursor: cursor || '*',
                    per_page: perPage,
                },
            });

            const attributes = data.attributes ?? data.data?.attributes ?? {};

            return {
                items: (attributes.items ?? []).map((item: Record<string, unknown>) => mapWorkshopItem(item)),
                total: Number(attributes.total ?? 0),
                nextCursor: attributes.next_cursor ?? null,
                appId: Number(attributes.app_id ?? 0),
                sync: mapWorkshopSync(attributes.sync as Record<string, unknown> | undefined),
            };
        },
        config
    );
};
