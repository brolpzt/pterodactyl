import http from '@/api/http';

export interface WalletTransaction {
    id: number;
    type: string;
    amount: number;
    balance_after: number | null;
    description: string | null;
    reference_type?: string | null;
    reference_id?: number | null;
    reference?: { server_name: string; server_uuid: string };
    created_at: string;
}

export interface BillingServer {
    id: number;
    uuid: string;
    name: string;
    status: string | null;
}

export interface BillingInfo {
    balance: number;
    transactions: WalletTransaction[];
    servers: BillingServer[];
    available_methods: string[];
}

export const getBillingInfo = (): Promise<BillingInfo> => {
    return http.get('/api/client/account/billing').then(({ data }) => data);
};

export const createDeposit = (amount: number, method: string): Promise<{ balance: number }> => {
    return http.post('/api/client/account/billing/deposit', { amount, method }).then(({ data }) => data);
};
