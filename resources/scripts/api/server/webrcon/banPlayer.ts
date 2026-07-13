import http from '@/api/http';

export default async (
    uuid: string,
    clientnum: number,
    minutes = 0,
    guid?: string | null,
): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/webrcon/ban`, { clientnum, minutes, guid });
};
