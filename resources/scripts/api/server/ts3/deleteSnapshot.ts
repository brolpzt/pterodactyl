import http from '@/api/http';

export default async (uuid: string, snapshotUuid: string): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/ts3/snapshots/${snapshotUuid}`);
};
