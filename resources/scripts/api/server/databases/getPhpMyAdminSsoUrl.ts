import http from '@/api/http';

export default async (uuid: string, database: string): Promise<string> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/databases/${database}/phpmyadmin`);
    const url = data?.attributes?.url ?? data?.url;
    if (!url || typeof url !== 'string') {
        throw new Error('phpMyAdmin SSO URL missing from response.');
    }

    return url;
};
