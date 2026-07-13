import http from '@/api/http';

export default async (uuid: string, message: string): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/webrcon/say`, { message });
};
