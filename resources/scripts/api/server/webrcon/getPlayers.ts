import http from '@/api/http';
import { WebRconPlayersResult } from './types';

export default async (uuid: string): Promise<WebRconPlayersResult> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/webrcon/players`);

    return data.attributes;
};
