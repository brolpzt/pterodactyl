import http from '@/api/http';
import { AmxxCvar } from './types';

export default async (uuid: string, name: string): Promise<AmxxCvar> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/amxx/cvars/${name}`);
    return data.attributes;
};
