import React, { useState, useEffect } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faGlobe, faWallet, faClock, faCalendar, faSpinner, faCog, faSearch } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Switch from '@/components/elements/Switch';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { useHistory } from 'react-router-dom';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import { getDeployOptions, getDeployEggVariables, createServer, DeployEgg, DeployPlan, DeployLocation, DeployEggVariable } from '@/api/deploy';
import { useCurrency } from '@/context/CurrencyContext';
import styled from 'styled-components';

const SelectableCard = styled.button<{ $selected?: boolean }>`
    ${tw`p-4 rounded border transition-all duration-200 flex flex-col items-start justify-center gap-1 shadow-sm text-left w-full`};
    ${({ $selected }) =>
        $selected
            ? tw`border-primary-500 bg-neutral-800`
            : tw`border-neutral-600 bg-neutral-900 hover:border-neutral-500 hover:bg-neutral-700`};
    &:hover {
        ${tw`transform scale-[1.01] shadow-md`};
    }
`;

const BILLING_TYPES = [
    { id: 'hourly', name: 'Hourly', icon: faClock },
    { id: 'monthly', name: 'Monthly', icon: faCalendar },
] as const;

/** Formats MB value: shows GB when >= 1024 (e.g. 4096 -> "4 GB", 512 -> "512 MB"). */
const formatMb = (mb: number): string => {
    if (mb >= 1024) {
        const gb = mb / 1024;
        return `${gb % 1 === 0 ? gb : gb.toFixed(1)} GB`;
    }
    return `${mb} MB`;
};

/** Converts 2-letter ISO country code to flag emoji (e.g. "BR" or "BR - São Paulo" -> 🇧🇷). Uses first 2 chars. */
const countryCodeToFlag = (code: string): string => {
    if (!code || code.length < 2) return '';
    const two = code.substring(0, 2).toUpperCase();
    if (two.charCodeAt(0) < 65 || two.charCodeAt(0) > 90 || two.charCodeAt(1) < 65 || two.charCodeAt(1) > 90) return '';
    const a = 0x1f1e6; // Regional Indicator A
    return String.fromCodePoint(
        a + (two.charCodeAt(0) - 65),
        a + (two.charCodeAt(1) - 65)
    );
};

const CreateServerContainer = () => {
    const history = useHistory();
    const { formatPrice } = useCurrency();
    const { addFlash, clearFlashes } = useFlash();
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [eggs, setEggs] = useState<DeployEgg[]>([]);
    const [locations, setLocations] = useState<DeployLocation[]>([]);
    const [walletBalance, setWalletBalance] = useState(0);
    const [selectedEgg, setSelectedEgg] = useState<DeployEgg | null>(null);
    const [selectedPlan, setSelectedPlan] = useState<DeployPlan | null>(null);
    const [eggSearch, setEggSearch] = useState('');
    const [selectedLocation, setSelectedLocation] = useState<number | null>(null);
    const [billingType, setBillingType] = useState<'hourly' | 'monthly'>('hourly');
    const [environment, setEnvironment] = useState<Record<string, string>>({});
    const [eggVariables, setEggVariables] = useState<DeployEggVariable[]>([]);
    const [variablesLoading, setVariablesLoading] = useState(false);

    useEffect(() => {
        getDeployOptions()
            .then((data) => {
                setEggs(data.eggs || []);
                setLocations(data.locations);
                setWalletBalance(data.wallet_balance);
                if (data.eggs?.length) {
                    const first = data.eggs[0];
                    setSelectedEgg(first);
                    setSelectedPlan(first.plans?.[0] ?? null);
                } else {
                    setSelectedEgg(null);
                    setSelectedPlan(null);
                }
                if (data.locations?.length) {
                    setSelectedLocation((s) => (s !== null ? s : data.locations[0].id));
                }
                setEnvironment({});
            })
            .catch(() => {
                addFlash({ key: 'deploy', type: 'error', message: 'Failed to load deploy options.' });
            })
            .finally(() => setLoading(false));
    }, [addFlash]);

    useEffect(() => {
        if (selectedEgg && !selectedPlan && selectedEgg.plans?.length) {
            setSelectedPlan(selectedEgg.plans[0]);
        } else if (selectedEgg && selectedPlan && !selectedEgg.plans?.some((p) => p.id === selectedPlan.id)) {
            setSelectedPlan(selectedEgg.plans?.[0] ?? null);
        }
    }, [selectedEgg, selectedPlan]);

    useEffect(() => {
        setEnvironment({});
        if (!selectedPlan) {
            setEggVariables([]);
            return;
        }
        setVariablesLoading(true);
        getDeployEggVariables(selectedPlan.id)
            .then((data) => setEggVariables(data.egg_variables || []))
            .catch(() => setEggVariables([]))
            .finally(() => setVariablesLoading(false));
    }, [selectedPlan]);

    const currentPlan = selectedPlan;
    const allowedBillingTypes = currentPlan
        ? BILLING_TYPES.filter((type) =>
            type.id === 'hourly' ? currentPlan.enable_hourly : currentPlan.enable_monthly)
        : BILLING_TYPES;

    useEffect(() => {
        if (!currentPlan) return;
        const hourlyAllowed = currentPlan.enable_hourly;
        const monthlyAllowed = currentPlan.enable_monthly;

        if (billingType === 'hourly' && !hourlyAllowed && monthlyAllowed) {
            setBillingType('monthly');
        } else if (billingType === 'monthly' && !monthlyAllowed && hourlyAllowed) {
            setBillingType('hourly');
        }
    }, [currentPlan, billingType]);

    const searchLower = eggSearch.trim().toLowerCase();
    const filteredEggs = eggs.filter((e) => {
        if (!searchLower) return true;
        const name = (e.egg_name || '').toLowerCase();
        const nest = (e.nest_name || '').toLowerCase();
        const desc = (e.egg_description || '').toLowerCase();
        return name.includes(searchLower) || nest.includes(searchLower) || desc.includes(searchLower);
    });

    const setEnvValue = (key: string, value: string) => {
        setEnvironment((prev) => ({ ...prev, [key]: value }));
    };

    const getEnvValue = (variable: DeployEggVariable): string => {
        if (variable.env_variable in environment) {
            return environment[variable.env_variable];
        }
        return variable.default_value ?? '';
    };

    const renderVariableInput = (variable: DeployEggVariable) => {
        const useSwitch = variable.rules.some(
            (v) => v === 'boolean' || v === 'in:0,1' || v === 'in:1,0' || v === 'in:true,false' || v === 'in:false,true'
        );
        const isStringSwitch = variable.rules.some((v) => v === 'string');
        const inRule = variable.rules.find((v) => v.startsWith('in:'));
        const selectValues = inRule ? inRule.replace(/^in:/, '').split(',') : [];

        const value = getEnvValue(variable);
        const displayValue = value || variable.default_value || '';

        if (useSwitch && variable.user_editable) {
            const checked = isStringSwitch ? displayValue === 'true' : displayValue === '1';
            return (
                <Switch
                    name={variable.env_variable}
                    checked={checked}
                    onChange={() => {
                        const next = isStringSwitch ? (checked ? 'false' : 'true') : checked ? '0' : '1';
                        setEnvValue(variable.env_variable, next);
                    }}
                />
            );
        }
        if (selectValues.length > 0 && variable.user_editable) {
            const selectValue = displayValue && selectValues.includes(displayValue) ? displayValue : (selectValues[0]?.trim() ?? '');
            return (
                <Select
                    value={selectValue}
                    onChange={(e) => setEnvValue(variable.env_variable, e.target.value)}
                    name={variable.env_variable}
                >
                    {selectValues.map((opt) => (
                        <option key={opt} value={opt.trim()}>
                            {opt.trim()}
                        </option>
                    ))}
                </Select>
            );
        }
        return (
            <Input
                value={variable.user_editable ? displayValue : variable.default_value}
                onChange={(e) => variable.user_editable && setEnvValue(variable.env_variable, e.target.value)}
                readOnly={!variable.user_editable}
                name={variable.env_variable}
                placeholder={variable.default_value}
            />
        );
    };
    const chargeToday = billingType === 'monthly' && currentPlan ? currentPlan.monthly_price : 0;
    const canDeploy = !submitting && selectedEgg && selectedPlan && selectedLocation !== null;
    const hasInsufficientFunds = billingType === 'monthly' && currentPlan && currentPlan.monthly_price > walletBalance;

    const buildEnvironmentPayload = (): Record<string, string> => {
        if (!eggVariables.length) return {};
        const payload: Record<string, string> = {};
        for (const v of eggVariables) {
            if (v.user_editable) {
                payload[v.env_variable] = environment[v.env_variable] ?? v.default_value ?? '';
            }
        }
        return payload;
    };

    const handleDeploy = () => {
        if (!canDeploy || hasInsufficientFunds || !selectedLocation || !selectedPlan) return;
        clearFlashes('deploy');
        setSubmitting(true);
        createServer({
            plan_id: selectedPlan.id,
            location_ids: [selectedLocation],
            billing_type: billingType,
            environment: buildEnvironmentPayload(),
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

    if (eggs.length === 0) {
        return (
            <PageContentBlock title={'Create New Server'}>
                <TitledGreyBox title={'No Plans Available'}>
                    <p css={tw`text-neutral-400 text-sm`}>
                        No deploy plans are configured. Please contact the administrator or configure deploy plans in{' '}
                        <code css={tw`bg-neutral-900 px-1 rounded`}>config/deploy.php</code>.
                    </p>
                    <Button css={tw`mt-4`} onClick={() => history.push('/')}>
                        Back to Dashboard
                    </Button>
                </TitledGreyBox>
            </PageContentBlock>
        );
    }

    return (
        <PageContentBlock title={'Create New Server'}>
            <FlashMessageRender byKey={'deploy'} css={tw`mb-4`} />
            <div css={tw`flex flex-col md:flex-row md:items-center justify-between mb-10 gap-6`}>
                <div>
                    <h1 css={tw`text-2xl font-black text-neutral-100`}>Deploy New Server</h1>
                    <p css={tw`text-neutral-500 text-sm font-medium`}>Choose your game, plan, location and billing cycle.</p>
                </div>
                <Button color={'grey'} onClick={() => history.push('/')}>
                    Cancel
                </Button>
            </div>

            <div css={tw`grid grid-cols-1 lg:grid-cols-3 gap-8`}>
                <div css={tw`lg:col-span-2 space-y-6`}>
                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center justify-between w-full`}>
                                <span css={tw`text-sm uppercase`}>1. Select Game (Egg)</span>
                            </div>
                        }
                    >
                        <p css={tw`text-neutral-400 text-sm mb-4`}>Choose the game or application for your server.</p>
                        <div css={tw`mb-4`}>
                            <label htmlFor="egg-search" css={tw`sr-only`}>
                                Search games
                            </label>
                            <div css={tw`relative`}>
                                <span css={tw`absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none text-neutral-500`}>
                                    <FontAwesomeIcon icon={faSearch} css={tw`text-sm`} />
                                </span>
                                <Input
                                    id="egg-search"
                                    type="text"
                                    placeholder="Search games..."
                                    value={eggSearch}
                                    onChange={(e) => setEggSearch(e.target.value)}
                                    autoComplete="off"
                                    css={tw`pl-10 w-full`}
                                />
                            </div>
                        </div>
                        <div css={tw`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-64 overflow-y-auto`}>
                            {filteredEggs.map((egg) => (
                                <SelectableCard
                                    key={egg.egg_id}
                                    type="button"
                                    onClick={() => {
                                        setSelectedEgg(egg);
                                        setSelectedPlan(egg.plans?.[0] ?? null);
                                    }}
                                    $selected={selectedEgg?.egg_id === egg.egg_id}
                                >
                                    <span css={tw`font-bold text-neutral-100`}>{egg.egg_name}</span>
                                    {egg.nest_name && (
                                        <span css={tw`text-[10px] text-neutral-500`}>{egg.nest_name}</span>
                                    )}
                                    <span css={tw`text-[10px] text-neutral-400`}>
                                        {egg.plans?.length ?? 0} plan{egg.plans?.length !== 1 ? 's' : ''}
                                    </span>
                                </SelectableCard>
                            ))}
                        </div>
                        {filteredEggs.length === 0 && (
                            <p css={tw`text-neutral-500 text-sm mt-2`}>No games match your search.</p>
                        )}
                    </TitledGreyBox>

                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center justify-between w-full`}>
                                <span css={tw`text-sm uppercase`}>2. Plan & Resources</span>
                            </div>
                        }
                    >
                        {selectedEgg ? (
                            selectedEgg.plans?.length > 0 ? (
                                <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-3`}>
                                    {[...selectedEgg.plans]
                                        .sort((a, b) => a.monthly_price - b.monthly_price)
                                        .map((plan) => (
                                        <SelectableCard
                                            key={plan.id}
                                            type="button"
                                            onClick={() => setSelectedPlan(plan)}
                                            $selected={selectedPlan?.id === plan.id}
                                        >
                                            <span css={tw`font-bold text-neutral-100`}>{plan.name}</span>
                                            {plan.description && (
                                                <span css={tw`text-xs text-neutral-400`}>{plan.description}</span>
                                            )}
                                            <div css={tw`flex flex-wrap gap-3 mt-2 text-xs`}>
                                                <span>{formatMb(plan.memory)} RAM</span>
                                                <span>{formatMb(plan.disk)} disk</span>
                                                <span>{plan.cpu}% CPU</span>
                                            </div>
                                            <span css={tw`font-mono font-semibold text-cyan-400 mt-1`}>
                                                {formatPrice(plan.hourly_rate)}/hr · {formatPrice(plan.monthly_price)}/mo
                                            </span>
                                        </SelectableCard>
                                        ))}
                                </div>
                            ) : (
                                <p css={tw`text-neutral-500 text-sm`}>No plans available for this game. Contact the administrator.</p>
                            )
                        ) : (
                            <p css={tw`text-neutral-500 text-sm`}>Select a game first.</p>
                        )}
                    </TitledGreyBox>

                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center justify-between w-full`}>
                                <div css={tw`flex items-center`}>
                                    <FontAwesomeIcon icon={faGlobe} css={tw`text-xs text-neutral-500 mr-2`} />
                                    <span css={tw`text-sm uppercase`}>3. Location</span>
                                </div>
                            </div>
                        }
                    >
                        <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-3`}>
                            {locations.map((loc) => {
                                const flag = countryCodeToFlag(loc.short);
                                return (
                                    <SelectableCard
                                        key={loc.id}
                                        type="button"
                                        onClick={() => setSelectedLocation(loc.id)}
                                        $selected={selectedLocation === loc.id}
                                    >
                                        <div css={tw`flex items-center gap-3 w-full`}>
                                            <span css={tw`text-2xl flex-shrink-0`} title={loc.short}>
                                                {flag || <FontAwesomeIcon icon={faGlobe} css={tw`text-neutral-500`} />}
                                            </span>
                                            <div css={tw`flex flex-col items-start min-w-0`}>
                                                <span css={tw`font-bold text-neutral-100`}>{loc.long || loc.short}</span>
                                                <span css={tw`text-[10px] text-neutral-500 uppercase tracking-tight`}>{loc.short}</span>
                                            </div>
                                        </div>
                                    </SelectableCard>
                                );
                            })}
                        </div>
                    </TitledGreyBox>

                    {selectedEgg && (
                        <TitledGreyBox
                            title={
                                <div css={tw`flex items-center justify-between w-full`}>
                                    <div css={tw`flex items-center`}>
                                        <FontAwesomeIcon icon={faCog} css={tw`text-xs text-neutral-500 mr-2`} />
                                        <span css={tw`text-sm uppercase`}>4. Server Configuration</span>
                                    </div>
                                </div>
                            }
                        >
                            {variablesLoading ? (
                                <div css={tw`flex justify-center py-8`}>
                                    <FontAwesomeIcon icon={faSpinner} spin css={tw`text-2xl text-neutral-400`} />
                                </div>
                            ) : eggVariables.length > 0 ? (
                                <>
                                    <p css={tw`text-neutral-400 text-sm mb-4`}>
                                        Configure your server variables. These can be changed later in the Startup tab.
                                    </p>
                                    <div css={tw`space-y-4`}>
                                        {eggVariables.map((variable) => (
                                    <div key={variable.env_variable}>
                                        <label css={tw`block text-xs text-neutral-500 font-semibold uppercase tracking-wider mb-2`}>
                                            {!variable.user_editable && (
                                                <span css={tw`bg-neutral-700 text-[10px] py-0.5 px-1.5 rounded mr-2`}>Read Only</span>
                                            )}
                                            {variable.name}
                                        </label>
                                        {renderVariableInput(variable)}
                                        {variable.description && (
                                            <p css={tw`mt-1 text-xs text-neutral-300`}>{variable.description}</p>
                                        )}
                                    </div>
                                ))}
                                    </div>
                                </>
                            ) : (
                                <p css={tw`text-neutral-500 text-sm`}>No configuration options for this plan.</p>
                            )}
                        </TitledGreyBox>
                    )}

                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center justify-between w-full`}>
                                <span css={tw`text-sm uppercase`}>
                                    {eggVariables.length ? '5' : '4'}. Billing Cycle
                                </span>
                                <span css={tw`text-[10px] bg-neutral-900 px-2 py-0.5 rounded-full text-neutral-500 font-medium`}>
                                    Hourly or monthly
                                </span>
                            </div>
                        }
                    >
                        <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-3`}>
                            {allowedBillingTypes.map(({ id, name, icon }) => (
                                <SelectableCard
                                    key={id}
                                    type="button"
                                    onClick={() => setBillingType(id)}
                                    $selected={billingType === id}
                                >
                                    <div css={tw`flex items-center gap-2`}>
                                        <FontAwesomeIcon
                                            icon={icon}
                                            css={[tw`text-xs`, billingType === id ? tw`text-primary-400` : tw`text-neutral-500`]}
                                        />
                                        <span css={tw`font-bold text-neutral-100`}>{name}</span>
                                    </div>
                                    <p css={tw`text-2xl font-mono font-black text-neutral-100`}>
                                        {formatPrice(id === 'hourly' ? (currentPlan?.hourly_rate ?? 0) : (currentPlan?.monthly_price ?? 0))}
                                    </p>
                                    <p css={tw`text-[10px] text-neutral-500`}>
                                        {id === 'hourly'
                                            ? 'Billed per hour. Flexible, destroy anytime.'
                                            : 'Billed upfront for the full period.'}
                                    </p>
                                </SelectableCard>
                            ))}
                        </div>
                        {currentPlan && allowedBillingTypes.length === 0 && (
                            <p css={tw`text-red-400 text-xs mt-3`}>
                                This plan has no billing cycle enabled. Please contact the administrator.
                            </p>
                        )}
                    </TitledGreyBox>
                </div>

                <div css={tw`lg:col-span-1`}>
                    <TitledGreyBox
                        className={'sticky top-24'}
                        title={
                            <div css={tw`flex items-center justify-between w-full`}>
                                <div css={tw`flex items-center`}>
                                    <FontAwesomeIcon icon={faWallet} css={tw`text-xs text-neutral-500 mr-2`} />
                                    <span css={tw`text-sm uppercase`}>Order Summary</span>
                                </div>
                            </div>
                        }
                    >
                        <div css={tw`mb-6`}>
                            <h4 css={tw`text-neutral-500 text-[10px] font-bold uppercase tracking-widest mb-3`}>Server Resources</h4>
                            {currentPlan && (
                                <>
                                    <div css={tw`flex items-center justify-between mb-2`}>
                                        <span>Memory (RAM)</span>
                                        <span css={tw`font-mono`}>{formatMb(currentPlan.memory)}</span>
                                    </div>
                                    <div css={tw`flex items-center justify-between mb-2`}>
                                        <span>Disk</span>
                                        <span css={tw`font-mono`}>{formatMb(currentPlan.disk)}</span>
                                    </div>
                                    <div css={tw`flex items-center justify-between mb-2`}>
                                        <span>CPU</span>
                                        <span css={tw`font-mono`}>{currentPlan.cpu}%</span>
                                    </div>
                                </>
                            )}
                        </div>

                        <div css={tw`border-t border-neutral-600 pt-6 mb-6`}>
                            <h4 css={tw`text-neutral-500 text-[10px] font-bold uppercase tracking-widest mb-3`}>Wallet Impact</h4>
                            <div css={tw`bg-neutral-900 p-4 rounded border border-neutral-700`}>
                                <div css={tw`flex items-center justify-between mb-3`}>
                                    <span css={tw`text-sm`}>Current Wallet Balance</span>
                                    <span css={tw`font-mono font-bold text-green-400`}>{formatPrice(walletBalance)}</span>
                                </div>
                                <div css={tw`flex items-center justify-between mb-3`}>
                                    <span css={tw`text-sm`}>Charge Today</span>
                                    <span css={tw`font-mono font-bold text-red-400`}>
                                        {chargeToday > 0 ? `-${formatPrice(chargeToday)}` : formatPrice(0)}
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
                                        {formatPrice(walletBalance - chargeToday)}
                                    </span>
                                </div>
                            </div>

                            {hasInsufficientFunds && (
                                <p css={tw`text-red-400 text-[10px] mt-3 flex items-center`}>
                                    <FontAwesomeIcon icon={faWallet} css={tw`mr-2`} />
                                    Insufficient funds for monthly cycle.
                                </p>
                            )}
                            {billingType === 'hourly' && currentPlan && (
                                <p css={tw`text-primary-400 text-[10px] mt-3`}>
                                    <FontAwesomeIcon icon={faClock} css={tw`mr-2`} />
                                    System will deduct {formatPrice(currentPlan.hourly_rate)}/hr from your balance.
                                </p>
                            )}
                        </div>

                        <Button
                            color={'primary'}
                            size={'large'}
                            css={tw`w-full`}
                            disabled={!canDeploy || hasInsufficientFunds || allowedBillingTypes.length === 0}
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
                    </TitledGreyBox>
                </div>
            </div>
        </PageContentBlock>
    );
};

export default CreateServerContainer;
