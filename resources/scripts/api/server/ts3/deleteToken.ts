import http from '@/api/http';

export default async (uuid: string, token: string): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/ts3/tokens`, { data: { token } });
};
