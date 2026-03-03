import http from '@/api/http';

export default async (serverUuid: string, addonUuid: string): Promise<void> => {
    await http.post(`/api/client/servers/${serverUuid}/addons/${addonUuid}/install`);
};
