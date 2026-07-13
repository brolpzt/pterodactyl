import http from '@/api/http';

export default async (uuid: string, name: string, value: string): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/webrcon/dvars`, { name, value });
};
