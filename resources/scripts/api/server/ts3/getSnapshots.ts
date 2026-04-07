import http from '@/api/http';
import { Ts3Snapshot } from './types';

export default async (uuid: string): Promise<Ts3Snapshot[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/ts3/snapshots`);
    return data.data || [];
};
