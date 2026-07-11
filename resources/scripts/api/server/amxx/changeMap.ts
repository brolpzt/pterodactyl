import http from '@/api/http';

export default async (uuid: string, map: string): Promise<{ map: string; command_sent: boolean }> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/amxx/maps`, { map });
    return data.attributes;
};
