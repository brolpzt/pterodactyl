import http from '@/api/http';
import { AmxxAdmin, AmxxOverview } from './types';

export interface AmxxAdminsPage {
    overview: AmxxOverview;
    admins: AmxxAdmin[];
}

export default async (uuid: string): Promise<AmxxAdminsPage> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/amxx/admins`);

    return {
        overview: data.attributes?.overview,
        admins: data.data || [],
    };
};
