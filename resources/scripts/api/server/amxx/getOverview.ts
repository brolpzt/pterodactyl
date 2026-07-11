import http from '@/api/http';
import { AmxxOverview } from './types';

export default async (uuid: string): Promise<AmxxOverview> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/amxx/overview`);
    return data.attributes;
};
