import http from '@/api/http';

export default async (uuid: string, clientnum: number, message: string): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/webrcon/tell`, { clientnum, message });
};
