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

export interface ExchangeRates {
    USD: number;
    EUR: number;
    BRL: number;
    updated_at: string | null;
}

export interface BillingInfo {
    balance: number;
    transactions: WalletTransaction[];
    servers: BillingServer[];
    available_methods: string[];
    user_cpf?: string | null;
    exchange_rates?: ExchangeRates | null;
}

export const getBillingInfo = (): Promise<BillingInfo> => {
    return http.get('/api/client/account/billing').then(({ data }) => data);
};

export const getExchangeRates = (): Promise<{ exchange_rates: ExchangeRates | null }> => {
    return http.get('/api/client/account/exchange-rates').then(({ data }) => data);
};

export interface DepositResult {
    balance: number;
    intent_id?: number | null;
    status?: string | null;
    gateway?: string | null;
    checkout_url?: string | null;
    transaction_id?: string | null;
    tax_id?: string | null;
    pix_code?: string | null;
    qr_code_image?: {
        image_base64?: string;
        image_mime_type?: string;
        image_data_url?: string;
    } | null;
    servers_restored?: number;
}

export const createDeposit = (amount: number, method: string, payerCpf?: string | null): Promise<DepositResult> => {
    return http.post('/api/client/account/billing/deposit', {
        amount,
        method,
        payer_cpf: payerCpf ?? undefined,
    }).then(({ data }) => data);
};
