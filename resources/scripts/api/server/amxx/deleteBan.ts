import http from '@/api/http';

export default async (uuid: string, banId: string, applyLive = true): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/amxx/bans/${encodeURIComponent(banId)}`, {
        params: { apply_live: applyLive },
    });
};
