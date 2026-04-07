import http from '@/api/http';
import { Ts3Overview } from './types';

export default async (uuid: string): Promise<Ts3Overview> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/ts3/overview`);
    return data.attributes;
};
