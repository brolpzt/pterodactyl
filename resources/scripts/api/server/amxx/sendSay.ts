import http from '@/api/http';

export default async (uuid: string, message: string): Promise<{ message: string; command_sent: boolean }> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/amxx/say`, { message });
    return data.attributes;
};
