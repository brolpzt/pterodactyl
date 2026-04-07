import http from '@/api/http';

export default async (uuid: string, ip: string, reason?: string, time = 0): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/ts3/bans`, { ip, reason: reason || null, time });
};
