import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import { Button } from '@/components/elements/button/index';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import getBans from '@/api/server/ts3/getBans';
import createBan from '@/api/server/ts3/createBan';
import deleteBan from '@/api/server/ts3/deleteBan';
import { Ts3Ban } from '@/api/server/ts3/types';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [bans, setBans] = useState<Ts3Ban[]>([]);
    const [loading, setLoading] = useState(true);
    const [ip, setIp] = useState('');
    const [reason, setReason] = useState('');
    const [busyId, setBusyId] = useState<string | null>(null);
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const load = async () => {
        setLoading(true);
        try {
            setBans(await getBans(uuid));
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:bans', error: err });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, [uuid]);

    const onCreate = async () => {
        if (!ip.trim()) return;
        clearFlashes('ts3:bans');
        setBusyId('create');
        try {
            await createBan(uuid, ip.trim(), reason.trim() || undefined);
            setIp('');
            setReason('');
            addFlash({ key: 'ts3:bans', type: 'success', message: 'Ban criado com sucesso.' });
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:bans', error: err });
        } finally {
            setBusyId(null);
        }
    };

    const onDelete = async (banId: string) => {
        clearFlashes('ts3:bans');
        setBusyId(banId);
        try {
            await deleteBan(uuid, banId);
            addFlash({ key: 'ts3:bans', type: 'success', message: 'Ban removido com sucesso.' });
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:bans', error: err });
        } finally {
            setBusyId(null);
        }
    };

    return (
        <ServerContentBlock title={'TS3 Bans'}>
            <FlashMessageRender byKey={'ts3:bans'} css={tw`mb-4`} />

            <TitledGreyBox title={'Adicionar ban'} css={tw`mb-6`}>
                <div css={tw`grid grid-cols-1 md:grid-cols-3 gap-3`}>
                    <div>
                        <Label>IP</Label>
                        <Input value={ip} onChange={(e) => setIp(e.currentTarget.value)} placeholder={'192.168.1.10'} />
                    </div>
                    <div>
                        <Label>Motivo</Label>
                        <Input value={reason} onChange={(e) => setReason(e.currentTarget.value)} placeholder={'Opcional'} />
                    </div>
                    <div css={tw`flex items-end`}>
                        <Button onClick={onCreate} disabled={busyId !== null || !ip.trim()}>
                            {busyId === 'create' ? <Spinner size={Spinner.Size.SMALL} /> : 'Adicionar'}
                        </Button>
                    </div>
                </div>
            </TitledGreyBox>

            <TitledGreyBox title={'Lista de bans'}>
                {loading ? (
                    <Spinner size={Spinner.Size.LARGE} centered />
                ) : bans.length === 0 ? (
                    <p css={[emptyStateText, tw`py-4`]}>Nenhum ban ativo.</p>
                ) : (
                    <table css={tw`w-full text-sm`}>
                        <thead>
                            <tr css={tw`text-neutral-400 border-b border-neutral-600`}>
                                <th css={tw`text-left py-2`}>ID</th>
                                <th css={tw`text-left py-2`}>IP</th>
                                <th css={tw`text-left py-2`}>Motivo</th>
                                <th css={tw`text-right py-2`}>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {bans.map((ban, index) => (
                                <tr key={`${ban.banid || index}`} css={tw`border-b border-neutral-700`}>
                                    <td css={tw`py-2`}>{ban.banid || '—'}</td>
                                    <td css={tw`py-2 font-mono`}>{ban.ip || '—'}</td>
                                    <td css={tw`py-2`}>{ban.reason || '—'}</td>
                                    <td css={tw`py-2 text-right`}>
                                        {ban.banid ? (
                                            <Button.Danger disabled={busyId !== null} onClick={() => onDelete(ban.banid!)}>
                                                {busyId === ban.banid ? <Spinner size={Spinner.Size.SMALL} /> : 'Remover'}
                                            </Button.Danger>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
