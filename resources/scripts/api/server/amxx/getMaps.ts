import http from '@/api/http';
import { AmxxMap } from './types';

export default async (uuid: string): Promise<AmxxMap[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/amxx/maps`);
    return data.data || [];
};
