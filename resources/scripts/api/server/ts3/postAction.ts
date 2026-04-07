import http from '@/api/http';

export type Ts3Action = 'start' | 'stop' | 'restart' | 'reinstall';

export default async (uuid: string, action: Ts3Action): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/ts3/actions/${action}`);
};
