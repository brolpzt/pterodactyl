import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { useStoreState } from '@/state/hooks';
import { getBillingInfo, getExchangeRates, ExchangeRates } from '@/api/account/billing';

const CURRENCY_STORAGE_KEY = 'pterodactyl_currency';

export interface CurrencyOption {
    code: string;
    symbol: string;
    name: string;
}

export const CURRENCIES: CurrencyOption[] = [
    { code: 'USD', symbol: '$', name: 'US Dollar' },
    { code: 'BRL', symbol: 'R$', name: 'Brazilian Real' },
    { code: 'EUR', symbol: '€', name: 'Euro' },
];

interface CurrencyContextValue {
    currency: CurrencyOption;
    setCurrency: (c: CurrencyOption) => void;
    exchangeRates: ExchangeRates | null;
    formatPrice: (usdAmount: number) => string;
}

const defaultCurrency = CURRENCIES[0];
const loadStoredCurrency = (): CurrencyOption => {
    try {
        const stored = localStorage.getItem(CURRENCY_STORAGE_KEY);
        if (stored) {
            const parsed = JSON.parse(stored);
            const found = CURRENCIES.find((c) => c.code === parsed?.code);
            if (found) return found;
        }
    } catch {
        /* ignore */
    }
    return defaultCurrency;
};

const CurrencyContext = createContext<CurrencyContextValue>({
    currency: defaultCurrency,
    setCurrency: () => {},
    exchangeRates: null,
    formatPrice: (n) => `$${n.toFixed(2)}`,
});

export const useCurrency = () => useContext(CurrencyContext);

export const CurrencyProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const user = useStoreState((state) => state.user.data);
    const [currency, setCurrencyState] = useState<CurrencyOption>(loadStoredCurrency);
    const [exchangeRates, setExchangeRates] = useState<ExchangeRates | null>(null);

    useEffect(() => {
        setCurrencyState(loadStoredCurrency());
    }, []);

    const setCurrency = useCallback((c: CurrencyOption) => {
        setCurrencyState(c);
        try {
            localStorage.setItem(CURRENCY_STORAGE_KEY, JSON.stringify({ code: c.code, symbol: c.symbol }));
        } catch {
            /* ignore */
        }
    }, []);

    useEffect(() => {
        if (!user) return;
        getExchangeRates()
            .then((res) => setExchangeRates(res.exchange_rates ?? null))
            .catch(() => setExchangeRates(null));
    }, [user]);

    const formatPrice = useCallback(
        (usdAmount: number): string => {
            if (currency.code === 'USD') {
                return `$${usdAmount.toFixed(2)}`;
            }
            const rate = exchangeRates?.[currency.code as keyof ExchangeRates] as number | undefined;
            if (!rate || rate <= 0) {
                return `$${usdAmount.toFixed(2)}`;
            }
            return `${currency.symbol}${(usdAmount * rate).toFixed(2)}`;
        },
        [currency, exchangeRates]
    );

    return (
        <CurrencyContext.Provider value={{ currency, setCurrency, exchangeRates, formatPrice }}>
            {children}
        </CurrencyContext.Provider>
    );
};
