import http from '@/api/http';

export default async (
    uuid: string,
    userid: number,
    message: string
): Promise<{ userid: number; message: string; command_sent: boolean }> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/amxx/psay`, { userid, message });
    return data.attributes;
};
