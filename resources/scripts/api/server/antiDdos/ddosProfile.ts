import http from '@/api/http';

export interface DdosProfileAlert {
    type: 'info' | 'success' | 'warning' | 'danger';
    title: string | null;
    /** Rendered by the panel (HTML escaped, safe links only), like panel alerts. */
    html: string;
}

export interface DdosProfileOption {
    id: number;
    label: string;
    alert: DdosProfileAlert | null;
}

export interface DdosProfileState {
    enabled: boolean;
    locked: boolean;
    custom: boolean;
    cooldown_minutes: number;
    next_change_at: string | null;
    current: { id: number | null; label: string; alert: DdosProfileAlert | null } | null;
    options: DdosProfileOption[];
}

export const getDdosProfile = async (uuid: string): Promise<DdosProfileState> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/anti-ddos/profile`);

    return data.attributes as DdosProfileState;
};

export const updateDdosProfile = async (uuid: string, profile: number): Promise<DdosProfileState> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/anti-ddos/profile`, { profile });

    return data.attributes as DdosProfileState;
};
