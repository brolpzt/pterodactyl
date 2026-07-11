import http from '@/api/http';

export default (serverUuid: string, publishedFileId: string): Promise<void> => {
    return http.post(`/api/client/servers/${serverUuid}/workshop/installed`, {
        published_file_id: publishedFileId,
    }).then(() => undefined);
};
