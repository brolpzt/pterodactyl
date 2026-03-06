import React from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faWallet, faCoins, faCreditCard, faHistory, faExchangeAlt } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import styled from 'styled-components';

const QuickButton = styled.button`
    ${tw`p-4 rounded border border-neutral-600 hover:border-neutral-500 hover:bg-neutral-700 transition-all duration-200 bg-neutral-900 flex flex-col items-center justify-center gap-1 shadow-sm`};
    &:hover { ${tw`transform scale-[1.02] shadow-md`}; }
`;

const BillingContainer = () => {
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
                                    <span css={tw`text-xs uppercase font-bold text-neutral-100`}>Current Balance</span>
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
                                <div css={tw`flex items-center`}>
                                    <span css={tw`text-xs uppercase font-bold text-neutral-100`}>Quick Recharge</span>
                                </div>
                                <span css={tw`text-[10px] bg-neutral-900 px-2 py-0.5 rounded-full text-neutral-500 uppercase font-black`}>Instant top-up</span>
                            </div>
                        }
                    >
                        <div css={tw`grid grid-cols-2 md:grid-cols-4 gap-4`}>
                            <QuickButton>
                                <p css={tw`text-lg font-black text-neutral-100`}>$5.00</p>
                                <span css={tw`text-[9px] uppercase font-bold text-neutral-500 tracking-tighter`}>Credits</span>
                            </QuickButton>
                            <QuickButton>
                                <p css={tw`text-lg font-black text-neutral-100`}>$10.00</p>
                                <span css={tw`text-[9px] uppercase font-bold text-neutral-500 tracking-tighter`}>Credits</span>
                            </QuickButton>
                            <QuickButton>
                                <p css={tw`text-lg font-black text-neutral-100`}>$20.00</p>
                                <span css={tw`text-[9px] uppercase font-bold text-neutral-500 tracking-tighter`}>Credits</span>
                            </QuickButton>
                            <QuickButton>
                                <p css={tw`text-lg font-black text-neutral-400`}>Custom</p>
                                <span css={tw`text-[9px] uppercase font-bold text-neutral-500 tracking-tighter`}>Amount</span>
                            </QuickButton>
                        </div>

                        <div css={tw`mt-4 p-4 bg-neutral-900/50 rounded-lg border border-neutral-700 flex items-center shadow-inner`}>
                            <div css={tw`h-8 w-8 rounded-full bg-neutral-800 flex items-center justify-center mr-4 shadow-md`}>
                                <FontAwesomeIcon icon={faCreditCard} css={tw`text-[11px] text-neutral-500`} />
                            </div>
                            <div>
                                <p css={tw`text-xs font-bold text-neutral-200`}>Supported Payment Methods</p>
                                <p css={tw`text-[10px] text-neutral-500 mt-0.5 font-medium`}>Stripe, PayPal, and Pix are available for worldwide payments.</p>
                            </div>
                        </div>
                    </TitledGreyBox>
                </div>
            </div>

            <TitledGreyBox
                title={
                    <div css={tw`flex items-center justify-between w-full`}>
                        <div css={tw`flex items-center`}>
                            <span css={tw`text-xs uppercase font-bold text-neutral-100`}>Recent Transactions</span>
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
