import http from '@/api/http';
import { AmxxAdmin, AmxxAuthType, AmxxPreset } from './types';

interface CreateAdminPayload {
    auth_type: AmxxAuthType;
    auth: string;
    password?: string;
    access_flags: string;
    nickname?: string;
    preset?: AmxxPreset;
    reload?: boolean;
}

export default async (uuid: string, payload: CreateAdminPayload): Promise<{ admin: AmxxAdmin; command_sent: boolean }> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/amxx/admins`, payload);
    return data.attributes;
};
