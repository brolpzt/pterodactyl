import http from '@/api/http';

export default (uuid: string, recordId: number): Promise<void> => {
    return http.delete(`/api/client/servers/${uuid}/dns/${recordId}`).then(() => undefined);
};
