import http from '@/api/http';

export default async (uuid: string, adminId: number, reload = true): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/amxx/admins/${adminId}`, {
        params: { reload },
    });
};
