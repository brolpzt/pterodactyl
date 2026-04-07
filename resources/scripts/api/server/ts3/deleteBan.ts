import http from '@/api/http';

export default async (uuid: string, banId: string | number): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/ts3/bans/${banId}`);
};
