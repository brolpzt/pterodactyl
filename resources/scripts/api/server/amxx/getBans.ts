import http from '@/api/http';
import { AmxxBan } from './types';

export default async (uuid: string): Promise<AmxxBan[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/amxx/bans`);
    return data.data || [];
};
