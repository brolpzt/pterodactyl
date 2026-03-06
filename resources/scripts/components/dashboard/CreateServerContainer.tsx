import React, { useState, useEffect } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faServer, faGlobe, faWallet, faClock, faCalendar, faSpinner } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import ContentBox from '@/components/elements/ContentBox';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { useHistory } from 'react-router-dom';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import { getDeployOptions, createServer, DeployPlan, DeployLocation } from '@/api/deploy';

const BILLING_TYPES = [
    { id: 'hourly', name: 'Hourly', icon: faClock },
    { id: 'monthly', name: 'Monthly', icon: faCalendar },
] as const;

const CreateServerContainer = () => {
    const history = useHistory();
    const { addFlash, clearFlashes } = useFlash();
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [plans, setPlans] = useState<DeployPlan[]>([]);
    const [locations, setLocations] = useState<DeployLocation[]>([]);
    const [walletBalance, setWalletBalance] = useState(0);
    const [serverName, setServerName] = useState('');
    const [selectedPlan, setSelectedPlan] = useState<string>('');
    const [selectedLocation, setSelectedLocation] = useState<number | null>(null);
    const [billingType, setBillingType] = useState<'hourly' | 'monthly'>('hourly');

    useEffect(() => {
        getDeployOptions()
            .then((data) => {
                setPlans(data.plans);
                setLocations(data.locations);
                setWalletBalance(data.wallet_balance);
                if (data.plans.length > 0) {
                    setSelectedPlan((s) => s || data.plans[0].id);
                }
                if (data.locations.length > 0) {
                    setSelectedLocation((s) => (s !== null ? s : data.locations[0].id));
                }
            })
            .catch(() => {
                addFlash({ key: 'deploy', type: 'error', message: 'Failed to load deploy options.' });
            })
            .finally(() => setLoading(false));
    }, [addFlash]);

    const currentPlan = plans.find((p) => p.id === selectedPlan);
    const chargeToday = billingType === 'monthly' && currentPlan ? currentPlan.monthly_price : 0;
    const canDeploy = !submitting && serverName.trim().length > 0 && selectedPlan && selectedLocation !== null;
    const hasInsufficientFunds = billingType === 'monthly' && currentPlan && currentPlan.monthly_price > walletBalance;

    const handleDeploy = () => {
        if (!canDeploy || hasInsufficientFunds || !selectedLocation) return;
        clearFlashes('deploy');
        setSubmitting(true);
        createServer({
            name: serverName.trim(),
            plan_id: selectedPlan,
            location_ids: [selectedLocation],
            billing_type: billingType,
        })
            .then((res) => {
                addFlash({ key: 'deploy', type: 'success', message: `Server "${res.server.name}" created successfully!` });
                history.push(`/server/${res.server.uuid}`);
            })
            .catch((err) => {
                const msg = err?.response?.data?.error || err?.message || 'Failed to create server.';
                addFlash({ key: 'deploy', type: 'error', message: msg });
            })
            .finally(() => setSubmitting(false));
    };

    if (loading) {
        return (
            <PageContentBlock title={'Create New Server'}>
                <div css={tw`flex justify-center py-20`}>
                    <FontAwesomeIcon icon={faSpinner} spin css={tw`text-4xl text-neutral-400`} />
                </div>
            </PageContentBlock>
        );
    }

    if (plans.length === 0) {
        return (
            <PageContentBlock title={'Create New Server'}>
                <ContentBox title={'No Plans Available'}>
                    <p css={tw`text-neutral-400`}>
                        No deploy plans are configured. Please contact the administrator or configure deploy plans in{' '}
                        <code>config/deploy.php</code>.
                    </p>
                    <Button css={tw`mt-4`} onClick={() => history.push('/')}>
                        Back to Dashboard
                    </Button>
                </ContentBox>
            </PageContentBlock>
        );
    }

    return (
        <PageContentBlock title={'Create New Server'}>
            <FlashMessageRender byKey={'deploy'} css={tw`mb-4`} />
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
                            <Input
                                placeholder={'e.g., My Awesome Server'}
                                value={serverName}
                                onChange={(e) => setServerName(e.target.value)}
                            />
                        </div>

                        <div css={tw`mb-4`}>
                            <label css={tw`block text-sm font-medium text-neutral-300 mb-2`}>Select Plan</label>
                            <Select value={selectedPlan} onChange={(e) => setSelectedPlan(e.target.value)}>
                                {plans.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name} — {p.memory}MB RAM, {p.disk}MB disk
                                    </option>
                                ))}
                            </Select>
                        </div>
                    </ContentBox>

                    <ContentBox title={'2. Location'}>
                        <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                            {locations.map((loc) => (
                                <button
                                    key={loc.id}
                                    type="button"
                                    onClick={() => setSelectedLocation(loc.id)}
                                    css={[
                                        tw`p-4 border-2 rounded-lg text-left transition-colors`,
                                        selectedLocation === loc.id
                                            ? tw`border-cyan-500 bg-neutral-800`
                                            : tw`border-neutral-700 bg-neutral-900 hover:border-neutral-500`,
                                    ]}
                                >
                                    <div css={tw`font-bold text-lg mb-1`}>{loc.long || loc.short}</div>
                                    <div css={tw`text-sm text-neutral-400`}>{loc.short}</div>
                                </button>
                            ))}
                        </div>
                    </ContentBox>

                    <ContentBox title={'3. Billing Cycle'}>
                        <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                            {BILLING_TYPES.map(({ id, name, icon }) => (
                                <button
                                    key={id}
                                    type="button"
                                    onClick={() => setBillingType(id)}
                                    css={[
                                        tw`p-5 border-2 rounded-lg text-left transition-all relative overflow-hidden`,
                                        billingType === id ? tw`border-cyan-500 bg-neutral-800` : tw`border-neutral-700 bg-neutral-900 hover:border-neutral-500`,
                                    ]}
                                >
                                    <div css={tw`flex items-center justify-between mb-2`}>
                                        <h3 css={tw`font-bold text-lg flex items-center`}>
                                            <FontAwesomeIcon
                                                icon={icon}
                                                css={[tw`mr-2`, billingType === id ? tw`text-cyan-400` : tw`text-neutral-500`]}
                                            />
                                            {name}
                                        </h3>
                                    </div>
                                    <p css={tw`text-3xl font-mono font-bold text-neutral-100 mb-2`}>
                                        ${id === 'hourly' ? (currentPlan?.hourly_rate ?? 0).toFixed(3) : (currentPlan?.monthly_price ?? 0).toFixed(2)}
                                    </p>
                                    <p css={tw`text-sm text-neutral-400`}>
                                        {id === 'hourly'
                                            ? 'Billed per hour. Flexible, destroy anytime.'
                                            : 'Billed upfront. Big discount for 24/7 usage.'}
                                    </p>
                                </button>
                            ))}
                        </div>
                    </ContentBox>
                </div>

                <div css={tw`lg:col-span-1`}>
                    <ContentBox title={'Order Summary'} css={tw`sticky top-24`}>
                        <div css={tw`mb-6`}>
                            <h4 css={tw`text-neutral-400 uppercase text-xs font-bold tracking-widest mb-3`}>Server Resources</h4>
                            {currentPlan && (
                                <>
                                    <div css={tw`flex items-center justify-between mb-2`}>
                                        <span>Memory (RAM)</span>
                                        <span css={tw`font-mono`}>{currentPlan.memory} MB</span>
                                    </div>
                                    <div css={tw`flex items-center justify-between mb-2`}>
                                        <span>Disk</span>
                                        <span css={tw`font-mono`}>{currentPlan.disk} MB</span>
                                    </div>
                                    <div css={tw`flex items-center justify-between mb-2`}>
                                        <span>CPU</span>
                                        <span css={tw`font-mono`}>{currentPlan.cpu}%</span>
                                    </div>
                                </>
                            )}
                        </div>

                        <div css={tw`border-t border-neutral-700 pt-6 mb-6`}>
                            <h4 css={tw`text-neutral-400 uppercase text-xs font-bold tracking-widest mb-3`}>Wallet Impact</h4>
                            <div css={tw`bg-neutral-900 p-4 rounded border border-neutral-700`}>
                                <div css={tw`flex items-center justify-between mb-3`}>
                                    <span css={tw`text-sm`}>Current Wallet Balance</span>
                                    <span css={tw`font-mono font-bold text-green-400`}>${walletBalance.toFixed(2)}</span>
                                </div>
                                <div css={tw`flex items-center justify-between mb-3`}>
                                    <span css={tw`text-sm`}>Charge Today</span>
                                    <span css={tw`font-mono font-bold text-red-400`}>
                                        {chargeToday > 0 ? `-$${chargeToday.toFixed(2)}` : '$0.00'}
                                    </span>
                                </div>
                                <div css={tw`border-t border-neutral-800 pt-3 flex items-center justify-between`}>
                                    <span css={tw`text-sm font-bold`}>Remaining Balance</span>
                                    <span
                                        css={[
                                            tw`font-mono font-bold`,
                                            hasInsufficientFunds ? tw`text-red-500` : tw`text-neutral-100`,
                                        ]}
                                    >
                                        ${(walletBalance - chargeToday).toFixed(2)}
                                    </span>
                                </div>
                            </div>

                            {hasInsufficientFunds && (
                                <p css={tw`text-red-400 text-xs mt-3 flex items-center`}>
                                    <FontAwesomeIcon icon={faWallet} css={tw`mr-2`} />
                                    Insufficient funds for monthly cycle.
                                </p>
                            )}
                            {billingType === 'hourly' && currentPlan && (
                                <p css={tw`text-cyan-400 text-xs mt-3`}>
                                    <FontAwesomeIcon icon={faClock} css={tw`mr-2`} />
                                    System will deduct ${currentPlan.hourly_rate.toFixed(3)}/hr from your balance.
                                </p>
                            )}
                        </div>

                        <Button
                            color={'primary'}
                            size={'large'}
                            css={tw`w-full`}
                            disabled={!canDeploy || hasInsufficientFunds}
                            onClick={handleDeploy}
                        >
                            {submitting ? (
                                <>
                                    <FontAwesomeIcon icon={faSpinner} spin css={tw`mr-2`} />
                                    Deploying...
                                </>
                            ) : (
                                'Deploy Server'
                            )}
                        </Button>
                    </ContentBox>
                </div>
            </div>
        </PageContentBlock>
    );
};

export default CreateServerContainer;
