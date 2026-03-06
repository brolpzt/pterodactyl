import React, { useState, useEffect } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faGlobe, faWallet, faClock, faCalendar, faSpinner, faCog } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Switch from '@/components/elements/Switch';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { useHistory } from 'react-router-dom';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import { getDeployOptions, getDeployEggVariables, createServer, DeployEgg, DeployLocation, DeployEggVariable } from '@/api/deploy';
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

const CreateServerContainer = () => {
    const history = useHistory();
    const { addFlash, clearFlashes } = useFlash();
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [eggs, setEggs] = useState<DeployEgg[]>([]);
    const [locations, setLocations] = useState<DeployLocation[]>([]);
    const [walletBalance, setWalletBalance] = useState(0);
    const [selectedEgg, setSelectedEgg] = useState<DeployEgg | null>(null);
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
                    setSelectedEgg(data.eggs[0]);
                } else {
                    setSelectedEgg(null);
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
        setEnvironment({});
        if (!selectedEgg) {
            setEggVariables([]);
            return;
        }
        setVariablesLoading(true);
        getDeployEggVariables(selectedEgg.plan_id)
            .then((data) => setEggVariables(data.egg_variables || []))
            .catch(() => setEggVariables([]))
            .finally(() => setVariablesLoading(false));
    }, [selectedEgg]);

    const currentPlan = selectedEgg?.plan;

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
    const canDeploy = !submitting && selectedEgg && selectedLocation !== null;
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
        if (!canDeploy || hasInsufficientFunds || !selectedLocation) return;
        clearFlashes('deploy');
        setSubmitting(true);
        createServer({
            plan_id: selectedEgg.plan_id,
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
                        <div css={tw`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3`}>
                            {eggs.map((egg) => (
                                <SelectableCard
                                    key={egg.plan_id}
                                    type="button"
                                    onClick={() => setSelectedEgg(egg)}
                                    $selected={selectedEgg?.plan_id === egg.plan_id}
                                >
                                    <span css={tw`font-bold text-neutral-100`}>{egg.egg_name}</span>
                                    <span css={tw`text-[10px] text-neutral-500`}>{egg.plan.name}</span>
                                </SelectableCard>
                            ))}
                        </div>
                    </TitledGreyBox>

                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center justify-between w-full`}>
                                <span css={tw`text-sm uppercase`}>2. Plan & Resources</span>
                            </div>
                        }
                    >
                        {selectedEgg ? (
                            <div css={tw`bg-neutral-800 rounded-lg p-4 border border-neutral-700`}>
                                <h4 css={tw`font-bold text-neutral-100 mb-3`}>{selectedEgg.plan.name}</h4>
                                <div css={tw`grid grid-cols-2 md:grid-cols-4 gap-4 text-sm`}>
                                    <div>
                                        <span css={tw`text-neutral-500 block text-[10px] uppercase`}>Memory</span>
                                        <span css={tw`font-mono font-semibold`}>{selectedEgg.plan.memory} MB</span>
                                    </div>
                                    <div>
                                        <span css={tw`text-neutral-500 block text-[10px] uppercase`}>Disk</span>
                                        <span css={tw`font-mono font-semibold`}>{selectedEgg.plan.disk} MB</span>
                                    </div>
                                    <div>
                                        <span css={tw`text-neutral-500 block text-[10px] uppercase`}>CPU</span>
                                        <span css={tw`font-mono font-semibold`}>{selectedEgg.plan.cpu}%</span>
                                    </div>
                                    <div>
                                        <span css={tw`text-neutral-500 block text-[10px] uppercase`}>Hourly</span>
                                        <span css={tw`font-mono font-semibold text-cyan-400`}>${selectedEgg.plan.hourly_rate.toFixed(3)}</span>
                                    </div>
                                </div>
                            </div>
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
                            {locations.map((loc) => (
                                <SelectableCard
                                    key={loc.id}
                                    type="button"
                                    onClick={() => setSelectedLocation(loc.id)}
                                    $selected={selectedLocation === loc.id}
                                >
                                    <span css={tw`font-bold text-neutral-100`}>{loc.long || loc.short}</span>
                                    <span css={tw`text-[10px] text-neutral-500 uppercase tracking-tight`}>{loc.short}</span>
                                </SelectableCard>
                            ))}
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
                            {BILLING_TYPES.map(({ id, name, icon }) => (
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
                                        ${id === 'hourly' ? (currentPlan?.hourly_rate ?? 0).toFixed(3) : (currentPlan?.monthly_price ?? 0).toFixed(2)}
                                    </p>
                                    <p css={tw`text-[10px] text-neutral-500`}>
                                        {id === 'hourly'
                                            ? 'Billed per hour. Flexible, destroy anytime.'
                                            : 'Billed upfront. Big discount for 24/7 usage.'}
                                    </p>
                                </SelectableCard>
                            ))}
                        </div>
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

                        <div css={tw`border-t border-neutral-600 pt-6 mb-6`}>
                            <h4 css={tw`text-neutral-500 text-[10px] font-bold uppercase tracking-widest mb-3`}>Wallet Impact</h4>
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
                                <p css={tw`text-red-400 text-[10px] mt-3 flex items-center`}>
                                    <FontAwesomeIcon icon={faWallet} css={tw`mr-2`} />
                                    Insufficient funds for monthly cycle.
                                </p>
                            )}
                            {billingType === 'hourly' && currentPlan && (
                                <p css={tw`text-primary-400 text-[10px] mt-3`}>
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
                    </TitledGreyBox>
                </div>
            </div>
        </PageContentBlock>
    );
};

export default CreateServerContainer;
