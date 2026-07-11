import http from '@/api/http';

export default (serverUuid: string, publishedFileId: string): Promise<void> => {
    return http.delete(`/api/client/servers/${serverUuid}/workshop/installed/${publishedFileId}`).then(() => undefined);
};
