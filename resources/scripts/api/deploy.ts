import http from '@/api/http';

export interface DeployEggVariable {
    name: string;
    description: string;
    env_variable: string;
    default_value: string;
    user_editable: boolean;
    rules: string[];
}

export interface DeployPlan {
    id: string;
    name: string;
    hourly_rate: number;
    monthly_price: number;
    memory: number;
    disk: number;
    cpu: number;
    egg_variables?: DeployEggVariable[];
}

export interface DeployLocation {
    id: number;
    short: string;
    long: string;
}

export interface DeployOptions {
    plans: DeployPlan[];
    locations: DeployLocation[];
    wallet_balance: number;
}

export const getDeployOptions = (): Promise<DeployOptions> => {
    return http.get('/api/client/deploy').then(({ data }) => data);
};

export const createServer = (params: {
    name: string;
    plan_id: string;
    location_ids: number[];
    billing_type: string;
    environment?: Record<string, string>;
}): Promise<{ success: boolean; server: { id: number; uuid: string; name: string } }> => {
    return http.post('/api/client/deploy', params).then(({ data }) => data);
};
