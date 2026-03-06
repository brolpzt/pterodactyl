import React, { useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faWallet, faCoins, faCreditCard, faExchangeAlt, faBolt } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import styled from 'styled-components';

const QUICK_AMOUNTS = [5, 10, 20] as const;

const QuickButton = styled.button<{ $selected?: boolean }>`
    ${tw`p-5 rounded-xl border transition-all duration-200 flex flex-col items-center justify-center gap-1.5 shadow-sm relative overflow-hidden`};
    ${(p) =>
        p.$selected
            ? tw`border-primary-500 bg-primary-500/15 shadow-lg ring-2 ring-primary-500/30`
            : tw`border-neutral-600 bg-neutral-900 hover:border-neutral-500 hover:bg-neutral-800`};
    &:hover:not([data-selected]) { ${tw`transform scale-[1.02] shadow-md`}; }
`;

const BillingContainer = () => {
    const [selectedAmount, setSelectedAmount] = useState<number | 'custom' | null>(null);
    const [customAmount, setCustomAmount] = useState('');

    const parsedCustom = parseFloat(customAmount);
    const isValidCustom = !isNaN(parsedCustom) && parsedCustom > 0;
    const displayAmount =
        selectedAmount === 'custom'
            ? customAmount && isValidCustom
                ? `$${parsedCustom.toFixed(2)}`
                : null
            : selectedAmount != null
              ? `$${selectedAmount.toFixed(2)}`
              : null;

    return (
        <PageContentBlock title={'Billing & Wallet'}>
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
                        <div css={tw`text-center py-2 mb-4`}>
                            <p css={tw`text-[10px] text-neutral-500 font-black uppercase tracking-widest mb-1`}>Available Credits</p>
                            <p css={tw`text-4xl font-mono font-black text-neutral-100`}>$14.50</p>
                            <p css={tw`text-[10px] text-neutral-500 mt-2 font-medium`}>Available for service charges</p>
                        </div>

                        <Button color={'primary'} css={tw`w-full font-bold shadow-lg`}>
                            <FontAwesomeIcon icon={faCoins} css={tw`mr-2 opacity-50 text-[10px]`} />
                            Add Funds to Wallet
                        </Button>
                    </TitledGreyBox>
                </div>

                <div css={tw`md:col-span-2`}>
                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center justify-between w-full`}>
                                <div css={tw`flex items-center gap-2`}>
                                    <FontAwesomeIcon icon={faBolt} css={tw`text-primary-400 text-sm`} />
                                    <span css={tw`text-sm font-bold uppercase tracking-wider text-neutral-200`}>Quick Recharge</span>
                                </div>
                                <span css={tw`text-[10px] bg-primary-500/20 text-primary-300 px-2.5 py-1 rounded-full uppercase font-bold tracking-wider border border-primary-500/30`}>
                                    Instant top-up
                                </span>
                            </div>
                        }
                    >
                        <div css={tw`grid grid-cols-2 md:grid-cols-4 gap-3`}>
                            {QUICK_AMOUNTS.map((amount) => (
                                <QuickButton
                                    key={amount}
                                    type="button"
                                    $selected={selectedAmount === amount}
                                    data-selected={selectedAmount === amount ? '' : undefined}
                                    onClick={() => setSelectedAmount(amount)}
                                >
                                    <p css={[tw`text-xl font-black`, selectedAmount === amount && tw`text-primary-300`]}>${amount.toFixed(2)}</p>
                                    <span css={tw`text-[10px] uppercase font-semibold text-neutral-500 tracking-tight`}>Credits</span>
                                </QuickButton>
                            ))}
                            <QuickButton
                                type="button"
                                $selected={selectedAmount === 'custom'}
                                data-selected={selectedAmount === 'custom' ? '' : undefined}
                                onClick={() => setSelectedAmount('custom')}
                            >
                                <p css={[tw`text-lg font-black`, selectedAmount === 'custom' && tw`text-primary-300`]}>Custom</p>
                                <span css={tw`text-[10px] uppercase font-semibold text-neutral-500 tracking-tight`}>Amount</span>
                            </QuickButton>
                        </div>

                        {selectedAmount === 'custom' && (
                            <div css={tw`mt-4 flex items-center gap-3`}>
                                <span css={tw`text-sm font-semibold text-neutral-400`}>$</span>
                                <input
                                    type="number"
                                    min="1"
                                    step="0.01"
                                    placeholder="0.00"
                                    value={customAmount}
                                    onChange={(e) => setCustomAmount(e.target.value)}
                                    css={tw`flex-1 px-4 py-3 rounded-lg bg-neutral-900 border border-neutral-600 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 text-neutral-100 font-mono font-bold text-lg outline-none transition-all`}
                                />
                            </div>
                        )}

                        {displayAmount && (
                            <div css={tw`mt-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 p-4 rounded-xl bg-neutral-900/60 border border-neutral-600`}>
                                <div css={tw`flex items-center gap-3`}>
                                    <div css={tw`h-10 w-10 rounded-lg bg-primary-500/20 flex items-center justify-center`}>
                                        <FontAwesomeIcon icon={faCoins} css={tw`text-primary-400`} />
                                    </div>
                                    <div>
                                        <p css={tw`text-xs font-bold text-neutral-400 uppercase tracking-wider`}>Amount to add</p>
                                        <p css={tw`text-xl font-mono font-black text-neutral-100`}>{displayAmount}</p>
                                    </div>
                                </div>
                                <Button color={'primary'} size={'large'} css={tw`font-bold shadow-lg whitespace-nowrap`}>
                                    <FontAwesomeIcon icon={faCreditCard} css={tw`mr-2`} />
                                    Proceed to Payment
                                </Button>
                            </div>
                        )}

                        <div css={tw`mt-4 p-4 rounded-xl bg-neutral-900/40 border border-neutral-700/80 flex items-center gap-4`}>
                            <div css={tw`h-10 w-10 rounded-lg bg-neutral-800 flex items-center justify-center shrink-0`}>
                                <FontAwesomeIcon icon={faCreditCard} css={tw`text-neutral-400`} />
                            </div>
                            <div>
                                <p css={tw`text-sm font-bold text-neutral-200`}>Supported Payment Methods</p>
                                <p css={tw`text-xs text-neutral-500 mt-0.5`}>Stripe, PayPal, and Pix available for worldwide payments.</p>
                            </div>
                        </div>
                    </TitledGreyBox>
                </div>
            </div>

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
                            <tr css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                <td css={tw`px-3 py-4 text-xs text-neutral-500 font-medium`}>Today, 14:30</td>
                                <td css={tw`px-3 py-4`}>
                                    <div css={tw`flex items-center`}>
                                        <span css={tw`bg-neutral-800 text-[9px] px-1.5 py-0.5 rounded mr-3 text-neutral-400 font-bold uppercase tracking-wider border border-neutral-600 shadow-sm`}>Hourly</span>
                                        <span css={tw`text-sm text-neutral-100 font-medium whitespace-nowrap overflow-hidden truncate max-w-[300px]`}>Hourly charge for server "My CS 1.6 Server" (10 hrs)</span>
                                    </div>
                                </td>
                                <td css={tw`px-3 py-4 text-sm font-black text-red-500 tracking-tighter`}>-$0.20</td>
                                <td css={tw`px-3 py-4 text-right`}>
                                    <span css={tw`bg-green-600 text-green-50 text-[9px] px-2 py-0.5 rounded uppercase font-black tracking-widest shadow-sm`}>Completed</span>
                                </td>
                            </tr>
                            <tr css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                <td css={tw`px-3 py-4 text-xs text-neutral-500 font-medium`}>Apr 02, 09:15</td>
                                <td css={tw`px-3 py-4`}>
                                    <div css={tw`flex items-center`}>
                                        <span css={tw`bg-neutral-800 text-[9px] px-1.5 py-0.5 rounded mr-3 text-neutral-400 font-bold uppercase tracking-wider border border-neutral-600 shadow-sm`}>Top-Up</span>
                                        <span css={tw`text-sm text-neutral-100 font-medium`}>Added funds via Pix</span>
                                    </div>
                                </td>
                                <td css={tw`px-3 py-4 text-sm font-black text-green-500 tracking-tighter`}>+$10.00</td>
                                <td css={tw`px-3 py-4 text-right`}>
                                    <span css={tw`bg-green-600 text-green-50 text-[9px] px-2 py-0.5 rounded uppercase font-black tracking-widest shadow-sm`}>Completed</span>
                                </td>
                            </tr>
                            <tr css={tw`hover:bg-neutral-600/20 transition-colors duration-100`}>
                                <td css={tw`px-3 py-4 text-xs text-neutral-500 font-medium`}>Mar 15, 18:00</td>
                                <td css={tw`px-3 py-4`}>
                                    <div css={tw`flex items-center`}>
                                        <span css={tw`bg-neutral-800 text-[9px] px-1.5 py-0.5 rounded mr-3 text-neutral-400 font-bold uppercase tracking-wider border border-neutral-600 shadow-sm`}>Monthly</span>
                                        <span css={tw`text-sm text-neutral-100 font-medium`}>Monthly renewal for server "Rust Clan Server"</span>
                                    </div>
                                </td>
                                <td css={tw`px-3 py-4 text-sm font-black text-red-500 tracking-tighter`}>-$15.00</td>
                                <td css={tw`px-3 py-4 text-right`}>
                                    <span css={tw`bg-green-600 text-green-50 text-[9px] px-2 py-0.5 rounded uppercase font-black tracking-widest shadow-sm`}>Completed</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TitledGreyBox>
        </PageContentBlock>
    );
};

export default BillingContainer;
