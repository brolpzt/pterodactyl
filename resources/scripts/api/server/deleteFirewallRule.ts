import http from '@/api/http';

export default (uuid: string, ruleId: number): Promise<void> => {
    return http.delete(`/api/client/servers/${uuid}/firewall/${ruleId}`).then(() => undefined);
};
