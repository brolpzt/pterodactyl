import useSWR, { SWRConfiguration } from 'swr';
import http, { PaginatedResult } from '@/api/http';

export interface Addon {
    id: number;
    uuid: string;
    name: string;
    description: string;
    containerImage: string;
    isActive: boolean;
    reinstallServer: boolean;
    categoryId: number | null;
    categoryName: string;
    createdAt: string;
    updatedAt: string;
}

export default (uuid: string, config?: SWRConfiguration) => {
    return useSWR<Addon[]>(
        ['server:addons', uuid],
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/addons`);
            return data.data.map((raw: any) => ({
                id: raw.attributes.id,
                uuid: raw.attributes.uuid,
                name: raw.attributes.name,
                description: raw.attributes.description,
                containerImage: raw.attributes.container_image,
                isActive: raw.attributes.is_active,
                reinstallServer: raw.attributes.reinstall_server,
                categoryId: raw.attributes.category_id,
                categoryName: raw.attributes.category_name,
                createdAt: raw.attributes.created_at,
                updatedAt: raw.attributes.updated_at,
            }));
        },
        config
    );
};
