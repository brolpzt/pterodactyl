import http from '@/api/http';

export default async (
    uuid: string,
    userid: number,
    damage = 0
): Promise<{ userid: number; damage: number; command_sent: boolean }> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/amxx/slap`, { userid, damage });
    return data.attributes;
};
