import http from '@/api/http';
import { WebRconDvar } from './types';

export default async (uuid: string): Promise<WebRconDvar[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/webrcon/dvars`);

    return data.data;
};
