import React, { useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faServer, faGamepad, faGlobe, faMicrochip, faWallet, faClock, faCalendar } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import ContentBox from '@/components/elements/ContentBox';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { useHistory } from 'react-router-dom';

const CreateServerContainer = () => {
    const history = useHistory();
    const [billingCycle, setBillingCycle] = useState<'hourly' | 'monthly'>('hourly');
    const [selectedGame, setSelectedGame] = useState('cs16');

    // Define mock data for pricing differences
    const pricing = {
        cs16: { hourly: 0.015, monthly: 4.00, ram: '1 GB', cpu: '1 vCPU' },
        minecraft: { hourly: 0.05, monthly: 12.00, ram: '4 GB', cpu: '2 vCPU' },
        rust: { hourly: 0.12, monthly: 25.00, ram: '12 GB', cpu: '4 vCPU' },
    };

    const currentPricing = pricing[selectedGame as keyof typeof pricing];

    return (
        <PageContentBlock title={'Create New Server'}>
            <div css={tw`flex justify-between items-center mb-6`}>
                <h1 css={tw`text-2xl font-bold flex items-center`}>
                    <FontAwesomeIcon icon={faServer} css={tw`mr-3 text-cyan-500`} />
                    Deploy New Server
                </h1>
                <Button color={'grey'} onClick={() => history.push('/')}>
                    Cancel
                </Button>
            </div>

            <div css={tw`grid grid-cols-1 lg:grid-cols-3 gap-8`}>
                <div css={tw`lg:col-span-2 space-y-6`}>
                    <ContentBox title={'1. Server Details'}>
                        <div css={tw`mb-4`}>
                            <label css={tw`block text-sm font-medium text-neutral-300 mb-2`}>Server Name</label>
                            <Input placeholder={'e.g., My Awesome CS 1.6 Server'} />
                        </div>

                        <div css={tw`mb-4`}>
                            <label css={tw`block text-sm font-medium text-neutral-300 mb-2`}>Select Game / Engine</label>
                            <Select value={selectedGame} onChange={(e) => setSelectedGame(e.target.value)}>
                                <option value="cs16">Counter-Strike 1.6 (Retro Plan)</option>
                                <option value="minecraft">Minecraft Spigot (Esports Plan)</option>
                                <option value="rust">Rust (HeavyDuty Plan)</option>
                            </Select>
                        </div>
                    </ContentBox>

                    <ContentBox title={'2. Location'}>
                        <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                            <button css={tw`p-4 border-2 rounded-lg text-left transition-colors border-cyan-500 bg-neutral-800`}>
                                <div css={tw`font-bold text-lg mb-1`}>🇧🇷 São Paulo, BR</div>
                                <div css={tw`text-sm text-neutral-400`}>Low Latency</div>
                            </button>
                            <button css={tw`p-4 border-2 rounded-lg text-left transition-colors border-neutral-700 bg-neutral-900 opacity-50 cursor-not-allowed`}>
                                <div css={tw`font-bold text-lg mb-1`}>🇺🇸 Miami, US</div>
                                <div css={tw`text-sm text-neutral-400`}>Out of Stock</div>
                            </button>
                        </div>
                    </ContentBox>

                    <ContentBox title={'3. Billing Cycle'}>
                        <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                            <button
                                onClick={() => setBillingCycle('hourly')}
                                css={[
                                    tw`p-5 border-2 rounded-lg text-left transition-all relative overflow-hidden`,
                                    billingCycle === 'hourly' ? tw`border-cyan-500 bg-neutral-800` : tw`border-neutral-700 bg-neutral-900 hover:border-neutral-500`
                                ]}
                            >
                                <div css={tw`flex items-center justify-between mb-2`}>
                                    <h3 css={tw`font-bold text-lg flex items-center`}>
                                        <FontAwesomeIcon icon={faClock} css={[tw`mr-2`, billingCycle === 'hourly' ? tw`text-cyan-400` : tw`text-neutral-500`]} />
                                        Hourly
                                    </h3>
                                </div>
                                <p css={tw`text-3xl font-mono font-bold text-neutral-100 mb-2`}>
                                    ${currentPricing.hourly}
                                </p>
                                <p css={tw`text-sm text-neutral-400`}>Billed per hour. Flexible, destroy anytime.</p>
                            </button>

                            <button
                                onClick={() => setBillingCycle('monthly')}
                                css={[
                                    tw`p-5 border-2 rounded-lg text-left transition-all relative overflow-hidden`,
                                    billingCycle === 'monthly' ? tw`border-cyan-500 bg-neutral-800` : tw`border-neutral-700 bg-neutral-900 hover:border-neutral-500`
                                ]}
                            >
                                {billingCycle === 'monthly' && (
                                    <div css={tw`absolute top-0 right-0 bg-cyan-500 text-neutral-900 font-bold text-xs py-1 px-3 rounded-bl-lg`}>
                                        BEST VALUE
                                    </div>
                                )}
                                <div css={tw`flex items-center justify-between mb-2`}>
                                    <h3 css={tw`font-bold text-lg flex items-center`}>
                                        <FontAwesomeIcon icon={faCalendar} css={[tw`mr-2`, billingCycle === 'monthly' ? tw`text-cyan-400` : tw`text-neutral-500`]} />
                                        Monthly
                                    </h3>
                                </div>
                                <p css={tw`text-3xl font-mono font-bold text-neutral-100 mb-2`}>
                                    ${currentPricing.monthly}
                                </p>
                                <p css={tw`text-sm text-neutral-400`}>Billed upfront. Big discount for 24/7 usage.</p>
                            </button>
                        </div>
                    </ContentBox>
                </div>

                <div css={tw`lg:col-span-1`}>
                    <ContentBox title={'Order Summary'} css={tw`sticky top-24`}>
                        <div css={tw`mb-6`}>
                            <h4 css={tw`text-neutral-400 uppercase text-xs font-bold tracking-widest mb-3`}>Server Resources</h4>
                            <div css={tw`flex items-center justify-between mb-2`}>
                                <span>Memory (RAM)</span>
                                <span css={tw`font-mono`}>{currentPricing.ram}</span>
                            </div>
                            <div css={tw`flex items-center justify-between mb-2`}>
                                <span>Processor (CPU)</span>
                                <span css={tw`font-mono`}>{currentPricing.cpu}</span>
                            </div>
                        </div>

                        <div css={tw`border-t border-neutral-700 pt-6 mb-6`}>
                            <h4 css={tw`text-neutral-400 uppercase text-xs font-bold tracking-widest mb-3`}>Wallet Impact</h4>

                            <div css={tw`bg-neutral-900 p-4 rounded border border-neutral-700`}>
                                <div css={tw`flex items-center justify-between mb-3`}>
                                    <span css={tw`text-sm`}>Current Wallet Balance</span>
                                    <span css={tw`font-mono font-bold text-green-400`}>$14.50</span>
                                </div>

                                <div css={tw`flex items-center justify-between mb-3`}>
                                    <span css={tw`text-sm`}>Charge Today</span>
                                    <span css={tw`font-mono font-bold text-red-400`}>
                                        {billingCycle === 'monthly' ? `-$${currentPricing.monthly.toFixed(2)}` : '$0.00'}
                                    </span>
                                </div>

                                <div css={tw`border-t border-neutral-800 pt-3 flex items-center justify-between`}>
                                    <span css={tw`text-sm font-bold`}>Remaining Balance</span>
                                    <span css={[tw`font-mono font-bold`, billingCycle === 'monthly' && currentPricing.monthly > 14.50 ? tw`text-red-500` : tw``]}>
                                        ${billingCycle === 'monthly' ? (14.50 - currentPricing.monthly).toFixed(2) : '14.50'}
                                    </span>
                                </div>
                            </div>

                            {billingCycle === 'monthly' && currentPricing.monthly > 14.50 && (
                                <p css={tw`text-red-400 text-xs mt-3 flex items-center`}>
                                    <FontAwesomeIcon icon={faWallet} css={tw`mr-2`} />
                                    Insufficient funds to clear monthly cycle.
                                </p>
                            )}
                            {billingCycle === 'hourly' && (
                                <p css={tw`text-cyan-400 text-xs mt-3`}>
                                    <FontAwesomeIcon icon={faClock} css={tw`mr-2`} />
                                    System will deduct ${currentPricing.hourly}/hr from your balance.
                                </p>
                            )}
                        </div>

                        <Button
                            color={'primary'}
                            size={'large'}
                            css={tw`w-full`}
                            disabled={billingCycle === 'monthly' && currentPricing.monthly > 14.50}
                        >
                            Deploy Server
                        </Button>
                    </ContentBox>
                </div>
            </div>
        </PageContentBlock>
    );
};

export default CreateServerContainer;
