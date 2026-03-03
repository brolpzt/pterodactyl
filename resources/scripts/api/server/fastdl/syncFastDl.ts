import http from '@/api/http';

export default async (uuid: string): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/fastdl/sync`)
            .then(() => resolve())
            .catch(reject);
    });
};
