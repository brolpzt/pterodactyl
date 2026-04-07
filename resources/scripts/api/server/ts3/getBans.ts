import http from '@/api/http';
import { Ts3Ban } from './types';

export default async (uuid: string): Promise<Ts3Ban[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/ts3/bans`);
    return data.data || [];
};
