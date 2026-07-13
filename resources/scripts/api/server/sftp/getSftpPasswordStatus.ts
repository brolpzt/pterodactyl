import http from '@/api/http';

export interface SftpPasswordStatus {
    hasPassword: boolean;
}

export default (uuid: string): Promise<SftpPasswordStatus> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/sftp/password`)
            .then(({ data }) =>
                resolve({
                    hasPassword: data.attributes.has_password,
                })
            )
            .catch(reject);
    });
};
