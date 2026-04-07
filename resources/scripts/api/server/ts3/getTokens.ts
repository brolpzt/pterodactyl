import http from '@/api/http';
import { Ts3Token } from './types';

export default async (uuid: string): Promise<Ts3Token[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/ts3/tokens`);
    return data.data || [];
};
