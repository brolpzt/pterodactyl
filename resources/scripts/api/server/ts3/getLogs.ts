import http from '@/api/http';

export interface Ts3LogEntry {
    l?: string;
    [key: string]: string | undefined;
}

export default async (uuid: string, lines = 100): Promise<Ts3LogEntry[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/ts3/logs`, { params: { lines } });
    return data.data || [];
};
