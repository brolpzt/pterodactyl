import http from '@/api/http';

export default async (uuid: string, addonId: number): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/addons/${addonId}/install`);
};
