import http from '@/api/http';
import { AmxxBan } from './types';

export default async (
    uuid: string,
    type: 'steamid' | 'ip',
    identifier: string,
    minutes = 0,
    reason?: string,
    applyLive = true,
    userid?: number
): Promise<{ ban: AmxxBan; command_sent: boolean }> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/amxx/bans`, {
        type,
        identifier,
        minutes,
        reason: reason || null,
        apply_live: applyLive,
        userid: userid ?? null,
    });
    return data.attributes;
};
