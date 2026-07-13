import http from '@/api/http';
import { WebRconMap } from './types';

export default async (uuid: string): Promise<WebRconMap[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/webrcon/maps`);

    return data.data;
};
