import http from '@/api/http';

export default async (uuid: string, map: string): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/webrcon/maps`, { map });
};
