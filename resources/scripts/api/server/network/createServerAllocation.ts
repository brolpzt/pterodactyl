import { Allocation } from '@/api/server/getServer';
import http from '@/api/http';
import { rawDataToServerAllocation } from '@/api/transformers';

export default async (uuid: string, portEnv?: string | null): Promise<Allocation> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/network/allocations`, {
        port_env: portEnv || undefined,
    });

    return rawDataToServerAllocation(data);
};
