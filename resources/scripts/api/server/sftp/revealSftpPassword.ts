import http from '@/api/http';

export default (uuid: string): Promise<string> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/sftp/password/reveal`)
            .then(({ data }) => resolve(data.attributes.password))
            .catch(reject);
    });
};
