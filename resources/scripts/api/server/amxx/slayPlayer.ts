import http from '@/api/http';

export default async (uuid: string, userid: number): Promise<{ userid: number; command_sent: boolean }> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/amxx/slay`, { userid });
    return data.attributes;
};
