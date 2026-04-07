import http from '@/api/http';

export interface Ts3QueryRow {
    [key: string]: string | undefined;
}

export default async (uuid: string, command: string): Promise<Ts3QueryRow[]> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/ts3/query/execute`, { command });
    return data.data || [];
};
