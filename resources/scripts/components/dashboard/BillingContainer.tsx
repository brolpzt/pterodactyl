import React from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faWallet, faCoins, faCreditCard, faHistory } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import ContentBox from '@/components/elements/ContentBox';

const BillingContainer = () => {
    return (
        <PageContentBlock title={'Billing & Wallet'}>
            <h1 css={tw`text-2xl font-bold mb-4`}>Wallet Overview</h1>

            <div css={tw`grid grid-cols-1 md:grid-cols-3 gap-6 mb-8`}>
                <div css={tw`bg-neutral-800 p-6 rounded-lg border border-neutral-700 shadow-md`}>
                    <div css={tw`flex items-center justify-between`}>
                        <h3 css={tw`text-lg font-semibold text-neutral-300`}>Current Balance</h3>
                        <FontAwesomeIcon icon={faWallet} css={tw`text-2xl text-cyan-500`} />
                    </div>
                    <p css={tw`text-4xl font-mono font-bold mt-4 text-neutral-100`}>$14.50</p>
                    <p css={tw`text-sm text-neutral-400 mt-2`}>Available for hourly & monthly charges</p>

                    <div css={tw`mt-6`}>
                        <Button color={'primary'} size={'large'} css={tw`w-full`}>
                            <FontAwesomeIcon icon={faCoins} css={tw`mr-2`} />
                            Add Funds
                        </Button>
                    </div>
                </div>

                <div css={tw`md:col-span-2 bg-neutral-800 p-6 rounded-lg border border-neutral-700 shadow-md`}>
                    <h3 css={tw`text-lg font-semibold text-neutral-300 mb-4`}>Quick Add</h3>
                    <div css={tw`grid grid-cols-2 md:grid-cols-4 gap-4`}>
                        <button css={tw`p-4 rounded border border-neutral-600 hover:border-cyan-500 hover:bg-neutral-700 transition-colors bg-neutral-900`}>
                            <p css={tw`text-xl font-bold`}>$5.00</p>
                        </button>
                        <button css={tw`p-4 rounded border border-neutral-600 hover:border-cyan-500 hover:bg-neutral-700 transition-colors bg-neutral-900`}>
                            <p css={tw`text-xl font-bold`}>$10.00</p>
                        </button>
                        <button css={tw`p-4 rounded border border-neutral-600 hover:border-cyan-500 hover:bg-neutral-700 transition-colors bg-neutral-900`}>
                            <p css={tw`text-xl font-bold`}>$20.00</p>
                        </button>
                        <button css={tw`p-4 rounded border border-neutral-600 hover:border-cyan-500 hover:bg-neutral-700 transition-colors bg-neutral-900 flex flex-col items-center justify-center`}>
                            <p css={tw`text-xl font-bold`}>Custom</p>
                        </button>
                    </div>

                    <div css={tw`mt-6 p-4 bg-neutral-900 rounded border-l-4 border-cyan-500`}>
                        <div css={tw`flex items-center`}>
                            <FontAwesomeIcon icon={faCreditCard} css={tw`text-neutral-400 mr-3 text-xl`} />
                            <div>
                                <p css={tw`text-sm font-semibold text-neutral-200`}>Payment Methods</p>
                                <p css={tw`text-xs text-neutral-400 mt-1`}>Stripe, PayPal, and Pix are supported.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <ContentBox title={'Recent Transactions'}>
                <div css={tw`overflow-x-auto`}>
                    <table css={tw`w-full text-left border-collapse`}>
                        <thead>
                            <tr css={tw`border-b border-neutral-700`}>
                                <th css={tw`py-3 px-4 font-semibold text-sm text-neutral-300`}>Date</th>
                                <th css={tw`py-3 px-4 font-semibold text-sm text-neutral-300`}>Description</th>
                                <th css={tw`py-3 px-4 font-semibold text-sm text-neutral-300`}>Amount</th>
                                <th css={tw`py-3 px-4 font-semibold text-sm text-neutral-300`}>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr css={tw`border-b border-neutral-800 hover:bg-neutral-800 transition-colors`}>
                                <td css={tw`py-4 px-4 text-sm text-neutral-400`}>Today, 14:30</td>
                                <td css={tw`py-4 px-4 text-sm text-neutral-200`}>
                                    <span css={tw`bg-neutral-700 text-xs px-2 py-1 rounded mr-2`}>Hourly</span>
                                    Hourly charge for server "My CS 1.6 Server" (10 hrs)
                                </td>
                                <td css={tw`py-4 px-4 text-sm font-mono text-red-400`}>-$0.20</td>
                                <td css={tw`py-4 px-4 text-sm`}>
                                    <span css={tw`bg-green-500/20 text-green-500 text-xs px-2 py-1 rounded-full border border-green-500`}>Completed</span>
                                </td>
                            </tr>
                            <tr css={tw`border-b border-neutral-800 hover:bg-neutral-800 transition-colors`}>
                                <td css={tw`py-4 px-4 text-sm text-neutral-400`}>Apr 02, 09:15</td>
                                <td css={tw`py-4 px-4 text-sm text-neutral-200`}>
                                    <span css={tw`bg-neutral-700 text-xs px-2 py-1 rounded mr-2`}>Top-Up</span>
                                    Added funds via Pix
                                </td>
                                <td css={tw`py-4 px-4 text-sm font-mono text-green-400`}>+$10.00</td>
                                <td css={tw`py-4 px-4 text-sm`}>
                                    <span css={tw`bg-green-500/20 text-green-500 text-xs px-2 py-1 rounded-full border border-green-500`}>Completed</span>
                                </td>
                            </tr>
                            <tr css={tw`hover:bg-neutral-800 transition-colors`}>
                                <td css={tw`py-4 px-4 text-sm text-neutral-400`}>Mar 15, 18:00</td>
                                <td css={tw`py-4 px-4 text-sm text-neutral-200`}>
                                    <span css={tw`bg-neutral-700 text-xs px-2 py-1 rounded mr-2`}>Monthly</span>
                                    Monthly renewal for server "Rust Clan Server"
                                </td>
                                <td css={tw`py-4 px-4 text-sm font-mono text-red-400`}>-$15.00</td>
                                <td css={tw`py-4 px-4 text-sm`}>
                                    <span css={tw`bg-green-500/20 text-green-500 text-xs px-2 py-1 rounded-full border border-green-500`}>Completed</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </ContentBox>
        </PageContentBlock>
    );
};

export default BillingContainer;
