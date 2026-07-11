import React, { useState } from 'react';
import { ServerContext } from '@/state/server';
import getServerFirewallRules, { FirewallRule } from '@/api/swr/getServerFirewallRules';
import addFirewallRule from '@/api/server/addFirewallRule';
import deleteFirewallRule from '@/api/server/deleteFirewallRule';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faTrash, faPlus } from '@fortawesome/free-solid-svg-icons';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';

// ── Ban row (single rule) ──────────────────────────────────────────────────

const FirewallRuleRow = ({ rule, onDelete }: { rule: FirewallRule; onDelete: (rule: FirewallRule) => void }) => {
    const [confirmOpen, setConfirmOpen] = useState(false);

    return (
        <React.Fragment>
            <Dialog.Confirm
                open={confirmOpen}
                title={'Remover Ban'}
                confirm={'Sim, remover'}
                onClose={() => setConfirmOpen(false)}
                onConfirmed={() => {
                    setConfirmOpen(false);
                    onDelete(rule);
                }}
            >
                Tem certeza que deseja remover o ban do IP <strong>{rule.ip}</strong>? A regra de
                iptables será removida imediatamente no Wings.
            </Dialog.Confirm>

            <tr css={tw`border-b border-neutral-600 last:border-b-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                <td css={tw`px-3 py-3`}>
                    <span css={tw`font-mono text-sm text-neutral-100`}>{rule.ip}</span>
                </td>
                <td css={tw`px-3 py-3`}>
                    <span css={tw`text-xs text-neutral-400`}>{rule.reason || '—'}</span>
                </td>
                <td css={tw`px-3 py-3 text-xs text-neutral-500`}>
                    {new Date(rule.createdAt).toLocaleString('pt-BR')}
                </td>
                <td css={tw`px-3 py-3 text-right`}>
                    <Button.Danger onClick={() => setConfirmOpen(true)}>
                        <FontAwesomeIcon icon={faTrash} css={tw`mr-1`} />
                        Remover
                    </Button.Danger>
                </td>
            </tr>
        </React.Fragment>
    );
};

// ── Add ban form ───────────────────────────────────────────────────────────

const AddRuleForm = ({ onAdd }: { onAdd: (ip: string, reason: string) => Promise<void> }) => {
    const [ip, setIp] = useState('');
    const [reason, setReason] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!ip.trim()) return;
        setLoading(true);
        try {
            await onAdd(ip.trim(), reason.trim());
            setIp('');
            setReason('');
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} css={tw`flex flex-col sm:flex-row gap-3 items-end`}>
            <div css={tw`flex-1`}>
                <Label htmlFor={'firewall-ip'} css={tw`text-xs mb-1`}>
                    Endereço IP
                </Label>
                <Input
                    id={'firewall-ip'}
                    type={'text'}
                    placeholder={'Ex: 192.168.1.100 ou 2001:db8::1'}
                    value={ip}
                    onChange={(e) => setIp(e.target.value)}
                    required
                    css={tw`font-mono text-sm`}
                />
            </div>
            <div css={tw`flex-1`}>
                <Label htmlFor={'firewall-reason'} css={tw`text-xs mb-1`}>
                    Motivo <span css={tw`text-neutral-500`}>(opcional)</span>
                </Label>
                <Input
                    id={'firewall-reason'}
                    type={'text'}
                    placeholder={'Ex: Abuso, spam, DDoS…'}
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                />
            </div>
            <Button.Danger className={'flex-shrink-0'} type={'submit'} disabled={loading || !ip.trim()}>
                {loading ? (
                    <Spinner size={Spinner.Size.SMALL} />
                ) : (
                    <>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                        Banir IP
                    </>
                )}
            </Button.Danger>
        </form>
    );
};

// ── Main container ─────────────────────────────────────────────────────────

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { data, error, isValidating, mutate } = getServerFirewallRules(uuid);
    const { clearFlashes, addFlash, clearAndAddHttpError } = useFlash();

    if (!data) {
        if (!error || (error && isValidating)) {
            return <Spinner centered size={Spinner.Size.LARGE} />;
        }
        return <ServerError title={'Oops!'} message={httpErrorToHuman(error)} onRetry={() => mutate()} />;
    }

    const handleAdd = async (ip: string, reason: string) => {
        clearFlashes('server:firewall');
        try {
            await addFirewallRule(uuid, ip, reason);
            await mutate();
            addFlash({
                key: 'server:firewall',
                type: 'success',
                message: `IP ${ip} foi banido com sucesso. A regra de iptables foi aplicada no Wings.`,
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'server:firewall', error: err });
        }
    };

    const handleDelete = async (rule: FirewallRule) => {
        clearFlashes('server:firewall');
        try {
            await deleteFirewallRule(uuid, rule.id);
            await mutate();
            addFlash({
                key: 'server:firewall',
                type: 'success',
                message: `Ban do IP ${rule.ip} foi removido com sucesso.`,
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'server:firewall', error: err });
        }
    };

    return (
        <ServerContentBlock title={'Firewall'}>
            <FlashMessageRender byKey={'server:firewall'} css={tw`mb-4`} />

            {/* Add rule */}
            <TitledGreyBox
                title={
                    <div css={tw`flex items-center`}>
                        <span css={tw`text-sm uppercase`}>Banir IP</span>
                    </div>
                }
                css={tw`mb-6`}
            >

                <AddRuleForm onAdd={handleAdd} />
            </TitledGreyBox>

            {/* Rules list */}
            <TitledGreyBox
                title={
                    <div css={tw`flex items-center justify-between w-full`}>
                        <div css={tw`flex items-center`}>
                            <span css={tw`text-sm uppercase`}>IPs Banidos</span>
                        </div>
                        <span css={tw`text-xs bg-neutral-900 px-2 py-0.5 rounded-full text-neutral-400`}>
                            {data.length} {data.length === 1 ? 'regra' : 'regras'}
                        </span>
                    </div>
                }
            >
                {data.length === 0 ? (
                    <p css={[emptyStateText, tw`py-6`]}>
                        Nenhum IP banido para este servidor.
                    </p>
                ) : (
                    <table css={tw`w-full text-left`}>
                        <thead>
                            <tr css={tw`text-xs text-neutral-400 uppercase border-b border-neutral-600`}>
                                <th css={tw`px-3 pb-2 font-semibold`}>IP</th>
                                <th css={tw`px-3 pb-2 font-semibold`}>Motivo</th>
                                <th css={tw`px-3 pb-2 font-semibold`}>Banido em</th>
                                <th css={tw`px-3 pb-2 font-semibold text-right`}>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.map((rule) => (
                                <FirewallRuleRow key={rule.id} rule={rule} onDelete={handleDelete} />
                            ))}
                        </tbody>
                    </table>
                )}
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
