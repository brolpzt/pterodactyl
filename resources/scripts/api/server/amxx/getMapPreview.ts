import http from '@/api/http';

export default async (uuid: string, map: string): Promise<string | null> => {
    try {
        const { data } = await http.get(`/api/client/servers/${uuid}/amxx/maps/${encodeURIComponent(map)}/preview`, {
            responseType: 'blob',
        });

        return URL.createObjectURL(data);
    } catch {
        return null;
    }
};
