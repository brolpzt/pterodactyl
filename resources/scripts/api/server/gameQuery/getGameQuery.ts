import http from '@/api/http';
import { GameQueryResult } from './types';

export default async (uuid: string): Promise<GameQueryResult> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/query`);
    return data.attributes;
};
