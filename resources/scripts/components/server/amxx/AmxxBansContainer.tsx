import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { Button } from '@/components/elements/button/index';
import { httpErrorToHuman } from '@/api/http';
import { ServerError } from '@/components/elements/ScreenBlock';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import getBans from '@/api/server/amxx/getBans';
import createBan from '@/api/server/amxx/createBan';
import deleteBan from '@/api/server/amxx/deleteBan';
import { AmxxBan } from '@/api/server/amxx/types';

const DURATION_OPTIONS = [
    { value: '0', label: 'Permanente' },
    { value: '30', label: '30 minutos' },
    { value: '60', label: '1 hora' },
    { value: '1440', label: '24 horas' },
    { value: '10080', label: '7 dias' },
];

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [bans, setBans] = useState<AmxxBan[]>([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [type, setType] = useState<'steamid' | 'ip'>('steamid');
    const [identifier, setIdentifier] = useState('');
    const [minutes, setMinutes] = useState('0');
    const [reason, setReason] = useState('');
    const [busyId, setBusyId] = useState<string | null>(null);
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const load = async () => {
        setLoading(true);
        setLoadError(null);
        try {
            setBans(await getBans(uuid));
        } catch (err) {
            setLoadError(httpErrorToHuman(err));
            clearAndAddHttpError({ key: 'amxx:bans', error: err });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, [uuid]);

    const onCreate = async () => {
        if (!identifier.trim()) return;
        clearFlashes('amxx:bans');
        setBusyId('create');
        try {
            const result = await createBan(uuid, type, identifier.trim(), parseInt(minutes, 10), reason.trim() || undefined);
            setIdentifier('');
            setReason('');
            addFlash({
                key: 'amxx:bans',
                type: 'success',
                message: result.command_sent
                    ? 'Ban aplicado com sucesso.'
                    : 'Ban salvo no ficheiro. Servidor offline — será aplicado ao reiniciar ou quando estiver online.',
            });
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:bans', error: err });
        } finally {
            setBusyId(null);
        }
    };

    const onDelete = async (banId: string) => {
        clearFlashes('amxx:bans');
        setBusyId(banId);
        try {
            await deleteBan(uuid, banId);
            addFlash({ key: 'amxx:bans', type: 'success', message: 'Ban removido com sucesso.' });
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:bans', error: err });
        } finally {
            setBusyId(null);
        }
    };

    const formatDuration = (ban: AmxxBan) => {
        if (ban.type === 'ip' || ban.permanent) return 'Permanente';
        if (ban.minutes < 60) return `${ban.minutes} min`;
        if (ban.minutes < 1440) return `${Math.round(ban.minutes / 60)} h`;
        return `${Math.round(ban.minutes / 1440)} dias`;
    };

    return (
        <ServerContentBlock title={'AMXX Bans'}>
            <FlashMessageRender byKey={'amxx:bans'} css={tw`mb-4`} />

            <TitledGreyBox title={'Adicionar ban'} css={tw`mb-6`}>
                <div css={tw`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3`}>
                    <div>
                        <Label>Tipo</Label>
                        <Select value={type} onChange={(e) => setType(e.currentTarget.value as 'steamid' | 'ip')}>
                            <option value={'steamid'}>SteamID</option>
                            <option value={'ip'}>IP</option>
                        </Select>
                    </div>
                    <div>
                        <Label>{type === 'steamid' ? 'SteamID' : 'IP'}</Label>
                        <Input
                            value={identifier}
                            onChange={(e) => setIdentifier(e.currentTarget.value)}
                            placeholder={type === 'steamid' ? 'STEAM_0:1:123456' : '192.168.1.10'}
                        />
                    </div>
                    <div>
                        <Label>Duração</Label>
                        <Select value={minutes} onChange={(e) => setMinutes(e.currentTarget.value)} disabled={type === 'ip'}>
                            {DURATION_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <Label>Motivo</Label>
                        <Input value={reason} onChange={(e) => setReason(e.currentTarget.value)} placeholder={'Opcional'} />
                    </div>
                </div>
                <div css={tw`mt-4`}>
                    <Button onClick={onCreate} disabled={busyId !== null || !identifier.trim()}>
                        {busyId === 'create' ? <Spinner size={Spinner.Size.SMALL} /> : 'Aplicar ban'}
                    </Button>
                </div>
            </TitledGreyBox>

            <TitledGreyBox title={'Lista de bans'}>
                {loading ? (
                    <Spinner size={Spinner.Size.LARGE} centered />
                ) : loadError ? (
                    <ServerError title={'Erro ao carregar bans'} message={loadError} />
                ) : bans.length === 0 ? (
                    <p css={[emptyStateText, tw`py-4`]}>Nenhum ban ativo.</p>
                ) : (
                    <table css={tw`w-full text-sm`}>
                        <thead>
                            <tr css={tw`text-neutral-400 border-b border-neutral-600`}>
                                <th css={tw`text-left py-2`}>Tipo</th>
                                <th css={tw`text-left py-2`}>Identificador</th>
                                <th css={tw`text-left py-2`}>Duração</th>
                                <th css={tw`text-left py-2`}>Motivo</th>
                                <th css={tw`text-right py-2`}>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {bans.map((ban) => (
                                <tr key={ban.id} css={tw`border-b border-neutral-700`}>
                                    <td css={tw`py-2 capitalize`}>{ban.type}</td>
                                    <td css={tw`py-2 font-mono text-xs`}>{ban.identifier}</td>
                                    <td css={tw`py-2`}>{formatDuration(ban)}</td>
                                    <td css={tw`py-2`}>{ban.reason || '—'}</td>
                                    <td css={tw`py-2 text-right`}>
                                        <Button.Danger disabled={busyId !== null} onClick={() => onDelete(ban.id)}>
                                            {busyId === ban.id ? <Spinner size={Spinner.Size.SMALL} /> : 'Remover'}
                                        </Button.Danger>
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
