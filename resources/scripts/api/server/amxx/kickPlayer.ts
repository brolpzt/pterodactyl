import http from '@/api/http';

export default async (uuid: string, userid: number, reason?: string): Promise<{ command_sent: boolean }> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/amxx/kick`, {
        userid,
        reason,
    });

    return data.attributes;
};
