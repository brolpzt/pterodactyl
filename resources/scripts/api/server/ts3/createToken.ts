import http from '@/api/http';

export default async (uuid: string, description?: string, groupId = 6): Promise<string | null> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/ts3/tokens`, {
        description: description || '',
        group_id: groupId,
    });

    return data.attributes?.token || null;
};
