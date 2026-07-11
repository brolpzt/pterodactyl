import http from '@/api/http';
import { AmxxAdmin, AmxxAuthType, AmxxPreset } from './types';

interface UpdateAdminPayload {
    auth_type: AmxxAuthType;
    auth: string;
    password?: string;
    access_flags: string;
    nickname?: string;
    enabled?: boolean;
    preset?: AmxxPreset;
    reload?: boolean;
}

export default async (
    uuid: string,
    adminId: number,
    payload: UpdateAdminPayload
): Promise<{ admin: AmxxAdmin; command_sent: boolean }> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/amxx/admins/${adminId}`, payload);
    return data.attributes;
};
