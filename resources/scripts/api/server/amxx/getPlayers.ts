import http from '@/api/http';
import { AmxxPlayersResult } from './types';

export default async (uuid: string): Promise<AmxxPlayersResult> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/amxx/players`);

    return data.attributes;
};
