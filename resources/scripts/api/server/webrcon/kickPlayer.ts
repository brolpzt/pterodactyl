import http from '@/api/http';

export default async (uuid: string, clientnum: number, reason?: string): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/webrcon/kick`, { clientnum, reason });
};
