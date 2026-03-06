import http from '@/api/http';

export interface WalletTransaction {
    id: number;
    type: string;
    amount: number;
    balance_after: number | null;
    description: string | null;
    created_at: string;
}

export interface BillingInfo {
    balance: number;
    transactions: WalletTransaction[];
    available_methods: string[];
}

export const getBillingInfo = (): Promise<BillingInfo> => {
    return http.get('/api/client/account/billing').then(({ data }) => data);
};

export const createDeposit = (amount: number, method: string): Promise<{ balance: number }> => {
    return http.post('/api/client/account/billing/deposit', { amount, method }).then(({ data }) => data);
};
