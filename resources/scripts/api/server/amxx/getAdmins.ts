import http from '@/api/http';
import { AmxxAdmin } from './types';

export default async (uuid: string): Promise<AmxxAdmin[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/amxx/admins`);
    return data.data || [];
};
