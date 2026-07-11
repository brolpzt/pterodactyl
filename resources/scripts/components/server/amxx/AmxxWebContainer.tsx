import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
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
import Can from '@/components/elements/Can';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import { ServerStatus } from '@/state/server';
import useGameQuery from '@/api/swr/getGameQuery';
import useAmxxPlayers from '@/api/swr/getAmxxPlayers';
import getOverview from '@/api/server/amxx/getOverview';
import kickPlayer from '@/api/server/amxx/kickPlayer';
import createBan from '@/api/server/amxx/createBan';
import { AmxxConsolePlayer, AmxxOverview } from '@/api/server/amxx/types';
import { supportsGameQuery } from '@/lib/supportsGameQuery';

const DURATION_OPTIONS = [
    { value: '0', label: 'Permanente' },
    { value: '30', label: '30 minutos' },
    { value: '60', label: '1 hora' },
    { value: '1440', label: '24 horas' },
    { value: '10080', label: '7 dias' },
];

const serverStateMessage = (status: ServerStatus): string => {
    switch (status) {
        case 'starting':
            return 'Servidor a iniciar. Aguarde para atualizar os dados em tempo real.';
        case 'stopping':
            return 'Servidor a parar.';
        case 'offline':
            return 'Servidor offline. Inicie o servidor para atualizar os dados em tempo real.';
        default:
            return 'Servidor offline. Inicie o servidor para atualizar os dados em tempo real.';
    }
};

const isPlayersUnavailableError = (error: unknown): boolean => {
    if (!error || typeof error !== 'object' || !('response' in error)) {
        return false;
    }

    const response = (error as { response?: { status?: number } }).response;
    const message = httpErrorToHuman(error).toLowerCase();

    return response?.status === 502
        || response?.status === 504
        || message.includes('online')
        || message.includes('offline')
        || message.includes('consola')
        || message.includes('expirou');
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const gamedig = ServerContext.useStoreState((state) => state.server.data!.gamedig);
    const eggId = ServerContext.useStoreState((state) => state.server.data!.eggId);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const queryEnabled = supportsGameQuery(gamedig, eggId);
    const isServerRunning = status === 'running';

    const { data: query, isLoading: queryLoading, error: queryError } = useGameQuery(queryEnabled && isServerRunning);
    const {
        data: playersData,
        error: playersError,
        isLoading: playersLoading,
        mutate: refreshPlayers,
    } = useAmxxPlayers(isServerRunning);

    const [overview, setOverview] = useState<AmxxOverview | null>(null);
    const [overviewLoading, setOverviewLoading] = useState(true);
    const [overviewError, setOverviewError] = useState<string | null>(null);
    const [busyUserId, setBusyUserId] = useState<number | null>(null);
    const [banTarget, setBanTarget] = useState<AmxxConsolePlayer | null>(null);
    const [banMinutes, setBanMinutes] = useState('0');
    const [banReason, setBanReason] = useState('');
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const runtimeStateMessage = useMemo(() => serverStateMessage(status), [status]);

    useEffect(() => {
        let cancelled = false;

        const loadOverview = async () => {
            setOverviewLoading(true);
            setOverviewError(null);
            try {
                const data = await getOverview(uuid);
                if (!cancelled) {
                    setOverview(data);
                }
            } catch (err) {
                if (!cancelled) {
                    setOverview(null);
                    setOverviewError(httpErrorToHuman(err));
                }
            } finally {
                if (!cancelled) {
                    setOverviewLoading(false);
                }
            }
        };

        loadOverview();

        return () => {
            cancelled = true;
        };
    }, [uuid]);

    useEffect(() => {
        if (!isServerRunning) {
            setBanTarget(null);
        }
    }, [isServerRunning]);

    const onKick = async (player: AmxxConsolePlayer) => {
        clearFlashes('amxx:web');
        setBusyUserId(player.userid);
        try {
            await kickPlayer(uuid, player.userid, 'Kick via AMXX Web');
            addFlash({ key: 'amxx:web', type: 'success', message: `Kick enviado para ${player.name}.` });
            await refreshPlayers();
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setBusyUserId(null);
        }
    };

    const onBan = async () => {
        if (!banTarget) return;

        const steamid = banTarget.steamid;
        if (!steamid || steamid === 'BOT' || !steamid.startsWith('STEAM_')) {
            addFlash({
                key: 'amxx:web',
                type: 'warning',
                message: 'Este jogador não tem SteamID válido para ban.',
            });
            return;
        }

        clearFlashes('amxx:web');
        setBusyUserId(banTarget.userid);
        try {
            const result = await createBan(
                uuid,
                'steamid',
                steamid,
                parseInt(banMinutes, 10),
                banReason.trim() || undefined
            );
            addFlash({
                key: 'amxx:web',
                type: 'success',
                message: result.command_sent
                    ? `Ban aplicado a ${banTarget.name}.`
                    : 'Ban salvo no ficheiro. Será aplicado quando o servidor estiver online.',
            });
            setBanTarget(null);
            setBanReason('');
            setBanMinutes('0');
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setBusyUserId(null);
        }
    };

    const players = playersData?.players ?? [];
    const playersOffline = !isServerRunning;
    const playersRuntimeError = isServerRunning && playersError && isPlayersUnavailableError(playersError);
    const playersLoadError = playersError && !isPlayersUnavailableError(playersError)
        ? httpErrorToHuman(playersError)
        : null;
    const playersUnavailableMessage = playersOffline
        ? runtimeStateMessage
        : 'Não foi possível obter jogadores via consola. Verifique se o processo está ativo.';

    return (
        <ServerContentBlock title={'AMXX Web'} showFlashKey={'amxx:web'}>
            <FlashMessageRender byKey={'amxx:web'} css={tw`mb-4`} />

            <div css={tw`grid gap-4 mb-4 md:grid-cols-2 xl:grid-cols-3`}>
                <TitledGreyBox title={'Estado do servidor'}>
                    {!isServerRunning ? (
                        <p css={emptyStateText}>{runtimeStateMessage}</p>
                    ) : queryLoading && !query ? (
                        <Spinner size={'small'} centered />
                    ) : queryError ? (
                        <p css={emptyStateText}>Servidor sem resposta à query.</p>
                    ) : query?.online ? (
                        <div css={tw`space-y-2 text-sm text-neutral-200`}>
                            <div><span css={tw`text-neutral-400`}>Hostname:</span> {query.hostname || '—'}</div>
                            <div><span css={tw`text-neutral-400`}>Mapa:</span> {query.map || '—'}</div>
                            <div><span css={tw`text-neutral-400`}>Versão:</span> {query.version || '—'}</div>
                            <div>
                                <span css={tw`text-neutral-400`}>Jogadores:</span>{' '}
                                {query.players}/{query.max_players || '?'}
                            </div>
                        </div>
                    ) : (
                        <p css={emptyStateText}>Servidor sem resposta à query.</p>
                    )}
                </TitledGreyBox>

                <TitledGreyBox title={'AMXX'}>
                    {overviewLoading ? (
                        <Spinner size={'small'} centered />
                    ) : overviewError ? (
                        <p css={emptyStateText}>{overviewError}</p>
                    ) : overview ? (
                        <div css={tw`space-y-2 text-sm text-neutral-200`}>
                            {!isServerRunning && (
                                <p css={[emptyStateText, tw`mb-2`]}>{runtimeStateMessage}</p>
                            )}
                            <div>
                                <span css={tw`text-neutral-400`}>Instalado:</span>{' '}
                                {overview.amxx_installed ? 'Sim' : 'Não'}
                            </div>
                            <div>
                                <span css={tw`text-neutral-400`}>users.ini:</span>{' '}
                                {overview.users_ini_exists ? 'Encontrado' : 'Ausente'}
                            </div>
                            <div>
                                <span css={tw`text-neutral-400`}>Diretório:</span> {overview.game_directory}
                            </div>
                            <div css={tw`flex flex-wrap gap-2 pt-2`}>
                                <Link to={`/server/${uuid}/amxx/admins`}>
                                    <Button size={Button.Sizes.Small}>Admins</Button>
                                </Link>
                                <Link to={`/server/${uuid}/amxx/bans`}>
                                    <Button size={Button.Sizes.Small}>Bans</Button>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <p css={emptyStateText}>Não foi possível carregar o estado AMXX.</p>
                    )}
                </TitledGreyBox>

                <TitledGreyBox title={'Consola (status)'}>
                    {playersOffline ? (
                        <p css={emptyStateText}>{runtimeStateMessage}</p>
                    ) : playersLoading && !playersData ? (
                        <Spinner size={'small'} centered />
                    ) : playersRuntimeError ? (
                        <p css={emptyStateText}>{playersUnavailableMessage}</p>
                    ) : playersLoadError ? (
                        <p css={emptyStateText}>{playersLoadError}</p>
                    ) : (
                        <div css={tw`space-y-2 text-sm text-neutral-200`}>
                            <div>
                                <span css={tw`text-neutral-400`}>Jogadores (consola):</span> {players.length}
                            </div>
                            {playersData?.hostname && (
                                <div>
                                    <span css={tw`text-neutral-400`}>Hostname:</span> {playersData.hostname}
                                </div>
                            )}
                            {playersData?.map && (
                                <div>
                                    <span css={tw`text-neutral-400`}>Mapa:</span> {playersData.map}
                                </div>
                            )}
                            {playersData?.queried_at && (
                                <div css={tw`text-xs text-neutral-500`}>
                                    Atualizado: {new Date(playersData.queried_at).toLocaleString()}
                                </div>
                            )}
                        </div>
                    )}
                </TitledGreyBox>
            </div>

            <TitledGreyBox title={'Jogadores online'}>
                {playersOffline ? (
                    <p css={emptyStateText}>{runtimeStateMessage}</p>
                ) : playersLoading && players.length === 0 && !playersRuntimeError ? (
                    <Spinner size={'large'} centered />
                ) : playersRuntimeError ? (
                    <p css={emptyStateText}>{playersUnavailableMessage}</p>
                ) : playersLoadError ? (
                    <p css={emptyStateText}>{playersLoadError}</p>
                ) : players.length === 0 ? (
                    <p css={emptyStateText}>Nenhum jogador encontrado via consola.</p>
                ) : (
                    <div css={tw`overflow-x-auto`}>
                        <table css={tw`w-full text-sm text-left text-neutral-200`}>
                            <thead>
                                <tr css={tw`border-b border-neutral-700 text-neutral-400 uppercase text-xs`}>
                                    <th css={tw`py-2 pr-4`}>#id</th>
                                    <th css={tw`py-2 pr-4`}>Nome</th>
                                    <th css={tw`py-2 pr-4`}>SteamID</th>
                                    <th css={tw`py-2 pr-4`}>Score</th>
                                    <th css={tw`py-2 pr-4`}>Ping</th>
                                    <th css={tw`py-2`}>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                {players.map((player) => (
                                    <tr key={`${player.userid}-${player.slot}`} css={tw`border-b border-neutral-800`}>
                                        <td css={tw`py-2 pr-4 font-mono`}>#{player.userid}</td>
                                        <td css={tw`py-2 pr-4`}>{player.name}</td>
                                        <td css={tw`py-2 pr-4 font-mono text-xs`}>{player.steamid}</td>
                                        <td css={tw`py-2 pr-4`}>{player.score}</td>
                                        <td css={tw`py-2 pr-4`}>{player.ping}</td>
                                        <td css={tw`py-2`}>
                                            <div css={tw`flex flex-wrap gap-2`}>
                                                <Button
                                                    size={Button.Sizes.Small}
                                                    disabled={busyUserId === player.userid}
                                                    onClick={() => onKick(player)}
                                                >
                                                    Kick
                                                </Button>
                                                <Can action={'firewall.create'}>
                                                    <Button
                                                        size={Button.Sizes.Small}
                                                        disabled={busyUserId === player.userid}
                                                        onClick={() => {
                                                            setBanTarget(player);
                                                            setBanReason('');
                                                            setBanMinutes('0');
                                                        }}
                                                    >
                                                        Ban
                                                    </Button>
                                                </Can>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </TitledGreyBox>

            {banTarget && (
                <TitledGreyBox title={`Banir ${banTarget.name}`} css={tw`mt-4`}>
                    <div css={tw`grid gap-4 md:grid-cols-2`}>
                        <div>
                            <Label>SteamID</Label>
                            <Input value={banTarget.steamid} readOnly />
                        </div>
                        <div>
                            <Label>Duração</Label>
                            <Select value={banMinutes} onChange={(e) => setBanMinutes(e.target.value)}>
                                {DURATION_OPTIONS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div css={tw`md:col-span-2`}>
                            <Label>Motivo</Label>
                            <Input value={banReason} onChange={(e) => setBanReason(e.target.value)} />
                        </div>
                    </div>
                    <div css={tw`flex gap-2 mt-4`}>
                        <Button disabled={busyUserId === banTarget.userid} onClick={onBan}>
                            Confirmar ban
                        </Button>
                        <Button.Text onClick={() => setBanTarget(null)}>Cancelar</Button.Text>
                    </div>
                </TitledGreyBox>
            )}
        </ServerContentBlock>
    );
};
