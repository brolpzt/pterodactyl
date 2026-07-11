import http from '@/api/http';

export default async (
    uuid: string,
    name: string,
    value: string
): Promise<{ name: string; value: string; command_sent: boolean }> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/amxx/cvars`, { name, value });
    return data.attributes;
};
