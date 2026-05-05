import React, { useState, useEffect } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faWallet, faExchangeAlt, faServer } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import PaymentModal from '@/components/dashboard/PaymentModal';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import { getBillingInfo, BillingInfo, WalletTransaction, BillingServer } from '@/api/account/billing';
import { useCurrency } from '@/context/CurrencyContext';
import styled from 'styled-components';
import useFlash from '@/plugins/useFlash';

const QUICK_AMOUNTS = [5, 10, 20] as const;

const QuickButton = styled.button`
    ${tw`p-4 rounded border border-neutral-600 bg-neutral-900 hover:border-neutral-500 hover:bg-neutral-700 transition-all duration-200 flex flex-col items-center justify-center gap-1 shadow-sm`};
    &:hover { ${tw`transform scale-[1.02] shadow-md`}; }
`;

const formatTransactionType = (type: string) => {
    if (type === 'deposit') return 'Top-Up';
    if (type === 'charge') return 'Charge';
    return type;
};

const formatTransactionDescription = (t: WalletTransaction) => {
    if (t.reference?.server_name) {
        return `${t.description || 'Charge'} — ${t.reference.server_name}`;
    }
    if (t.type === 'deposit') return t.description || 'Added funds';
    return t.description || 'Service charge';
};

const BillingContainer = () => {
    const { formatPrice } = useCurrency();
    const { addFlash, clearFlashes } = useFlash();
    const [paymentModalVisible, setPaymentModalVisible] = useState(false);
    const [paymentModalAmount, setPaymentModalAmount] = useState<number | null>(null);
    const [billing, setBilling] = useState<BillingInfo | null>(null);
    const [loading, setLoading] = useState(true);

    const refreshBilling = () => {
        getBillingInfo()
            .then(setBilling)
            .catch(() => setBilling(null))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        refreshBilling();
    }, []);

    useEffect(() => {
        const query = new URLSearchParams(window.location.search);
        const paymentStatus = query.get('payment');
        if (!paymentStatus) return;

        clearFlashes('billing');

        if (paymentStatus === 'success') {
            addFlash({
                key: 'billing',
                type: 'success',
                title: 'Payment Confirmed',
                message: 'Your payment was received successfully. Your wallet balance has been updated.',
            });
        } else if (paymentStatus === 'cancelled') {
            addFlash({
                key: 'billing',
                type: 'error',
                title: 'Payment Cancelled',
                message: 'The payment was cancelled before completion.',
            });
        }

        query.delete('payment');
        const nextSearch = query.toString();
        const nextUrl = `${window.location.pathname}${nextSearch ? `?${nextSearch}` : ''}${window.location.hash}`;
        window.history.replaceState({}, document.title, nextUrl);
    }, [addFlash, clearFlashes]);

    const openPaymentModal = (amount: number | null) => {
        setPaymentModalAmount(amount);
        setPaymentModalVisible(true);
    };

    const balance = billing?.balance ?? 0;

    return (
        <PageContentBlock title={'Billing & Wallet'}>
            <FlashMessageRender byKey={'billing'} css={tw`mb-4`} />
            <div css={tw`flex flex-col md:flex-row md:items-center justify-between mb-10 gap-6`}>
                <div>
                    <h1 css={tw`text-2xl font-black text-neutral-100`}>Wallet & Billing</h1>
                    <p css={tw`text-neutral-500 text-sm font-medium`}>Manage your account balance and track service charges.</p>
                </div>
            </div>

            <div css={tw`grid grid-cols-1 md:grid-cols-3 gap-8 mb-12`}>
                <div css={tw`md:col-span-1`}>
                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center justify-between w-full`}>
                                <div css={tw`flex items-center`}>
                                    <span css={tw`text-sm uppercase`}>Current Balance</span>
                                </div>
                                <FontAwesomeIcon icon={faWallet} css={tw`text-xs text-neutral-500`} />
                            </div>
                        }
                    >
                        {loading ? (
                            <div css={tw`flex justify-center py-8`}>
                                <Spinner size={'large'} />
                            </div>
                        ) : (
                            <div css={tw`text-center py-2`}>
                                <p css={tw`text-[10px] text-neutral-500 font-black uppercase tracking-widest mb-1`}>Available Credits</p>
                                <p css={tw`text-4xl font-mono font-black text-neutral-100`}>{formatPrice(balance)}</p>
                                <p css={tw`text-[10px] text-neutral-500 mt-2 font-medium`}>Available for service charges</p>
                            </div>
                        )}
                    </TitledGreyBox>
                </div>

                <div css={tw`md:col-span-2`}>
                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center justify-between w-full`}>
                                <div css={tw`flex items-center`}>
                                    <span css={tw`text-sm uppercase`}>Add Funds to Wallet</span>
                                </div>
                                <span css={tw`text-[10px] bg-neutral-900 px-2 py-0.5 rounded-full text-neutral-500 font-medium`}>Select how much you want to add in credits</span>
                            </div>
                        }
                    >
                        <div css={tw`grid grid-cols-2 md:grid-cols-4 gap-3`}>
                            {QUICK_AMOUNTS.map((amount) => (
                                <QuickButton key={amount} type="button" onClick={() => openPaymentModal(amount)}>
                                    <p css={tw`text-xl font-black text-neutral-100`}>{formatPrice(amount)}</p>
                                    <span css={tw`text-[10px] uppercase font-semibold text-neutral-500 tracking-tight`}>Credits</span>
                                </QuickButton>
                            ))}
                            <QuickButton type="button" onClick={() => openPaymentModal(null)}>
                                <p css={tw`text-lg font-black text-neutral-400`}>Outro valor</p>
                                <span css={tw`text-[10px] uppercase font-semibold text-neutral-500 tracking-tight`}>Amount</span>
                            </QuickButton>
                        </div>

                        <PaymentModal
                            visible={paymentModalVisible}
                            onDismissed={() => setPaymentModalVisible(false)}
                            onSuccess={refreshBilling}
                            availableMethods={billing?.available_methods ?? []}
                            initialAmount={paymentModalAmount}
                        />
                    </TitledGreyBox>
                </div>
            </div>

            {billing?.servers && billing.servers.length > 0 && (
                <TitledGreyBox
                    className={'mb-8'}
                    title={
                        <div css={tw`flex items-center justify-between w-full`}>
                            <div css={tw`flex items-center`}>
                                <FontAwesomeIcon icon={faServer} css={tw`text-xs text-neutral-500 mr-2`} />
                                <span css={tw`text-sm uppercase`}>O que está sendo cobrado</span>
                            </div>
                        </div>
                    }
                >
                    <p css={tw`text-xs text-neutral-500 mb-4`}>
                        Seus servidores ativos. As cobranças aparecem em Transações Recentes quando aplicadas.
                    </p>
                    <div css={tw`space-y-2`}>
                        {billing.servers.map((server) => (
                            <div
                                key={server.id}
                                css={tw`flex items-center justify-between p-3 rounded bg-neutral-900/50 border border-neutral-700`}
                            >
                                <div css={tw`flex items-center gap-3`}>
                                    <FontAwesomeIcon icon={faServer} css={tw`text-neutral-500`} />
                                    <div>
                                        <p css={tw`text-sm font-bold text-neutral-100`}>{server.name}</p>
                                        <p css={tw`text-[10px] text-neutral-500 font-mono`}>{server.uuid}</p>
                                    </div>
                                </div>
                                <a
                                    href={`/server/${server.uuid}`}
                                    css={tw`text-xs font-semibold text-primary-400 hover:text-primary-300`}
                                >
                                    Ver servidor →
                                </a>
                            </div>
                        ))}
                    </div>
                </TitledGreyBox>
            )}

            <TitledGreyBox
                title={
                    <div css={tw`flex items-center justify-between w-full`}>
                        <div css={tw`flex items-center`}>
                            <span css={tw`text-sm uppercase`}>Recent Transactions</span>
                        </div>
                        <FontAwesomeIcon icon={faExchangeAlt} css={tw`text-xs text-neutral-500 opacity-30`} />
                    </div>
                }
            >
                <div css={tw`overflow-x-auto`}>
                    <table css={tw`w-full text-left`}>
                        <thead>
                            <tr css={tw`text-xs text-neutral-400 uppercase border-b border-neutral-600`}>
                                <th css={tw`px-3 pb-3 font-semibold`}>Date & Time</th>
                                <th css={tw`px-3 pb-3 font-semibold`}>Description</th>
                                <th css={tw`px-3 pb-3 font-semibold`}>Amount</th>
                                <th css={tw`px-3 pb-3 font-semibold text-right`}>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan={4} css={tw`px-3 py-8 text-center`}>
                                        <Spinner size={'small'} />
                                    </td>
                                </tr>
                            ) : !billing?.transactions?.length ? (
                                <tr>
                                    <td colSpan={4} css={tw`px-3 py-8 text-center text-neutral-500 text-sm`}>
                                        No transactions yet.
                                    </td>
                                </tr>
                            ) : (
                                billing.transactions.map((t) => (
                                    <tr key={t.id} css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                        <td css={tw`px-3 py-4 text-xs text-neutral-500 font-medium`}>
                                            {new Date(t.created_at).toLocaleString()}
                                        </td>
                                        <td css={tw`px-3 py-4`}>
                                            <div css={tw`flex items-center`}>
                                                <span css={tw`bg-neutral-800 text-[9px] px-1.5 py-0.5 rounded mr-3 text-neutral-400 font-bold uppercase tracking-wider border border-neutral-600 shadow-sm`}>
                                                    {formatTransactionType(t.type)}
                                                </span>
                                                <span css={tw`text-sm text-neutral-100 font-medium whitespace-nowrap overflow-hidden truncate max-w-[300px]`}>
                                                    {formatTransactionDescription(t)}
                                                </span>
                                            </div>
                                        </td>
                                        <td css={tw`px-3 py-4 text-sm font-black tracking-tighter`, t.amount >= 0 ? tw`text-green-500` : tw`text-red-500`}>
                                            {t.amount >= 0 ? '+' : '-'}{formatPrice(Math.abs(t.amount))}
                                        </td>
                                        <td css={tw`px-3 py-4 text-right`}>
                                            <span css={tw`bg-green-600 text-green-50 text-[9px] px-2 py-0.5 rounded uppercase font-black tracking-widest shadow-sm`}>Completed</span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </TitledGreyBox>
        </PageContentBlock>
    );
};

export default BillingContainer;
