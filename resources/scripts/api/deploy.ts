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
    id: number;
    name: string;
    description?: string;
    hourly_rate: number;
    monthly_price: number;
    memory: number;
    disk: number;
    cpu: number;
}

export interface DeployEgg {
    egg_id: number;
    egg_name: string;
    egg_description?: string;
    nest_name?: string;
    plans: DeployPlan[];
}

export interface DeployLocation {
    id: number;
    short: string;
    long: string;
}

export interface DeployOptions {
    eggs: DeployEgg[];
    locations: DeployLocation[];
    wallet_balance: number;
}

export const getDeployOptions = (): Promise<DeployOptions> => {
    return http.get('/api/client/deploy').then(({ data }) => data);
};

export const getDeployEggVariables = (planId: number): Promise<{ egg_variables: DeployEggVariable[] }> => {
    return http.get(`/api/client/deploy/variables/${planId}`).then(({ data }) => data);
};

export const createServer = (params: {
    plan_id: number;
    location_ids: number[];
    billing_type: string;
    environment?: Record<string, string>;
    name?: string;
}): Promise<{ success: boolean; server: { id: number; uuid: string; name: string } }> => {
    return http.post('/api/client/deploy', params).then(({ data }) => data);
};
