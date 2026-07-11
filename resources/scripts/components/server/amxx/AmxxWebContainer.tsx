import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
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
import slapPlayer from '@/api/server/amxx/slapPlayer';
import slayPlayer from '@/api/server/amxx/slayPlayer';
import createBan from '@/api/server/amxx/createBan';
import AmxxWebLivePanel from '@/components/server/amxx/AmxxWebLivePanel';
import { AmxxConsolePlayer, AmxxOverview } from '@/api/server/amxx/types';
import { supportsGameQuery } from '@/lib/supportsGameQuery';

type DisplayPlayer = AmxxConsolePlayer & { source: 'console' | 'query' };
type BanType = 'steamid' | 'ip';

const formatQueryTime = (seconds?: number): string | undefined => {
    if (seconds == null || seconds < 0) {
        return undefined;
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);

    if (hours > 0) {
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
};

const parsePlayerIp = (address?: string): string | null => {
    if (!address) {
        return null;
    }

    const host = address.split(':')[0];
    return /^\d{1,3}(\.\d{1,3}){3}$/.test(host) ? host : null;
};

const isValidSteamId = (steamid: string): boolean =>
    Boolean(steamid && steamid !== 'BOT' && steamid !== '—' && steamid.startsWith('STEAM_'));

const defaultBanType = (player: AmxxConsolePlayer): BanType => {
    if (isValidSteamId(player.steamid)) {
        return 'steamid';
    }

    if (parsePlayerIp(player.address)) {
        return 'ip';
    }

    return 'steamid';
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
        || message.includes('console')
        || message.includes('expirou')
        || message.includes('timed out');
};

export default () => {
    const { t } = useTranslation('strings');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const gamedig = ServerContext.useStoreState((state) => state.server.data!.gamedig);
    const eggId = ServerContext.useStoreState((state) => state.server.data!.eggId);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const queryEnabled = supportsGameQuery(gamedig, eggId);
    const isServerRunning = status === 'running';

    const {
        data: query,
        isLoading: queryLoading,
        error: queryError,
        mutate: refreshQuery,
    } = useGameQuery(queryEnabled && isServerRunning);
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
    const [refreshing, setRefreshing] = useState(false);
    const [banTarget, setBanTarget] = useState<AmxxConsolePlayer | null>(null);
    const [banType, setBanType] = useState<BanType>('steamid');
    const [banMinutes, setBanMinutes] = useState('0');
    const [banReason, setBanReason] = useState('');
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const durationOptions = useMemo(
        () => [
            { value: '0', label: t('server_amxx_web.duration_permanent') },
            { value: '30', label: t('server_amxx_web.duration_30m') },
            { value: '60', label: t('server_amxx_web.duration_1h') },
            { value: '1440', label: t('server_amxx_web.duration_24h') },
            { value: '10080', label: t('server_amxx_web.duration_7d') },
        ],
        [t]
    );

    const runtimeStateMessage = useMemo(() => {
        switch (status) {
            case 'starting':
                return t('server_amxx_web.starting');
            case 'stopping':
                return t('server_amxx_web.stopping');
            case 'offline':
            default:
                return t('server_amxx_web.offline');
        }
    }, [status, t]);

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

    const consolePlayers = playersData?.players ?? [];

    const displayPlayers = useMemo((): DisplayPlayer[] => {
        if (consolePlayers.length > 0) {
            return consolePlayers.map((player) => ({ ...player, source: 'console' as const }));
        }

        if (isServerRunning && query?.online && query.player_list?.length) {
            return query.player_list.map((player, index) => ({
                userid: 0,
                slot: index + 1,
                name: player.name || '—',
                steamid: '—',
                score: player.score ?? 0,
                ping: player.ping ?? 0,
                loss: 0,
                state: 'query',
                connected: formatQueryTime(player.time),
                source: 'query' as const,
            }));
        }

        return [];
    }, [consolePlayers, isServerRunning, query]);

    const usingQueryFallback = consolePlayers.length === 0 && displayPlayers.length > 0;

    const onRefresh = async () => {
        if (!isServerRunning) {
            return;
        }

        setRefreshing(true);
        try {
            await Promise.all([
                refreshPlayers(),
                queryEnabled ? refreshQuery() : Promise.resolve(),
            ]);
        } finally {
            setRefreshing(false);
        }
    };

    const onKick = async (player: DisplayPlayer) => {
        if (!canActionPlayer(player)) {
            return;
        }

        clearFlashes('amxx:web');
        setBusyUserId(player.userid);
        try {
            await kickPlayer(uuid, player.userid, t('server_amxx_web.kick_reason'));
            addFlash({
                key: 'amxx:web',
                type: 'success',
                message: t('server_amxx_web.kick_success', { name: player.name }),
            });
            await refreshPlayers();
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setBusyUserId(null);
        }
    };

    const onSlap = async (player: DisplayPlayer) => {
        if (!canActionPlayer(player)) {
            return;
        }

        clearFlashes('amxx:web');
        setBusyUserId(player.userid);
        try {
            await slapPlayer(uuid, player.userid, 0);
            addFlash({
                key: 'amxx:web',
                type: 'success',
                message: t('server_amxx_web.slap_success', { name: player.name }),
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setBusyUserId(null);
        }
    };

    const onSlay = async (player: DisplayPlayer) => {
        if (!canActionPlayer(player)) {
            return;
        }

        clearFlashes('amxx:web');
        setBusyUserId(player.userid);
        try {
            await slayPlayer(uuid, player.userid);
            addFlash({
                key: 'amxx:web',
                type: 'success',
                message: t('server_amxx_web.slay_success', { name: player.name }),
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setBusyUserId(null);
        }
    };

    const onBan = async () => {
        if (!banTarget) {
            return;
        }

        const steamid = banTarget.steamid;
        const ip = parsePlayerIp(banTarget.address);
        let type: BanType = banType;
        let identifier = '';

        if (type === 'steamid') {
            if (!isValidSteamId(steamid)) {
                addFlash({
                    key: 'amxx:web',
                    type: 'warning',
                    message: t('server_amxx_web.ban_no_steamid'),
                });
                return;
            }
            identifier = steamid;
        } else {
            if (!ip) {
                addFlash({
                    key: 'amxx:web',
                    type: 'warning',
                    message: t('server_amxx_web.ban_no_ip'),
                });
                return;
            }
            identifier = ip;
        }

        clearFlashes('amxx:web');
        setBusyUserId(banTarget.userid);
        try {
            const result = await createBan(
                uuid,
                type,
                identifier,
                parseInt(banMinutes, 10),
                banReason.trim() || undefined,
                true,
                banTarget.userid > 0 ? banTarget.userid : undefined
            );
            addFlash({
                key: 'amxx:web',
                type: 'success',
                message: result.command_sent
                    ? t('server_amxx_web.ban_success_applied', { name: banTarget.name })
                    : t('server_amxx_web.ban_success_saved'),
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

    const openBanModal = (player: DisplayPlayer) => {
        setBanTarget(player);
        setBanType(defaultBanType(player));
        setBanReason('');
        setBanMinutes('0');
    };

    const playersOffline = !isServerRunning;
    const playersRuntimeError = isServerRunning && playersError && isPlayersUnavailableError(playersError);
    const playersLoadError = playersError && !isPlayersUnavailableError(playersError)
        ? httpErrorToHuman(playersError)
        : null;
    const playersUnavailableMessage = playersOffline
        ? runtimeStateMessage
        : t('server_amxx_web.players_unavailable');

    const banTargetIp = banTarget ? parsePlayerIp(banTarget.address) : null;
    const banTargetHasSteam = banTarget ? isValidSteamId(banTarget.steamid) : false;
    const banTargetHasIp = Boolean(banTargetIp);
    const canActionPlayer = (player: DisplayPlayer) => player.source === 'console' && player.userid > 0;
    const canKickPlayer = (player: DisplayPlayer) => canActionPlayer(player);
    const canBanPlayer = (player: DisplayPlayer) =>
        player.source === 'console' && (isValidSteamId(player.steamid) || Boolean(parsePlayerIp(player.address)));
    const currentMap = query?.map || playersData?.map || null;

    return (
        <ServerContentBlock title={t('server_amxx_web.title')}>
            <FlashMessageRender byKey={'amxx:web'} css={tw`mb-4`} />

            <div css={tw`flex justify-end mb-4`}>
                <Button
                    size={Button.Sizes.Small}
                    disabled={!isServerRunning || refreshing}
                    onClick={onRefresh}
                >
                    {refreshing ? <Spinner size={'small'} /> : t('server_amxx_web.refresh')}
                </Button>
            </div>

            <div css={tw`grid gap-4 mb-4 md:grid-cols-2 xl:grid-cols-3`}>
                <TitledGreyBox title={t('server_amxx_web.server_status')}>
                    {!isServerRunning ? (
                        <p css={emptyStateText}>{runtimeStateMessage}</p>
                    ) : queryLoading && !query ? (
                        <Spinner size={'small'} centered />
                    ) : queryError ? (
                        <p css={emptyStateText}>{t('server_amxx_web.query_no_response')}</p>
                    ) : query?.online ? (
                        <div css={tw`space-y-2 text-sm text-neutral-200`}>
                            <div>
                                <span css={tw`text-neutral-400`}>{t('server_amxx_web.hostname')}:</span>{' '}
                                {query.hostname || '—'}
                            </div>
                            <div>
                                <span css={tw`text-neutral-400`}>{t('server_amxx_web.map')}:</span>{' '}
                                {query.map || '—'}
                            </div>
                            <div>
                                <span css={tw`text-neutral-400`}>{t('server_amxx_web.version')}:</span>{' '}
                                {query.version || '—'}
                            </div>
                            <div>
                                <span css={tw`text-neutral-400`}>{t('server_amxx_web.players')}:</span>{' '}
                                {query.players}/{query.max_players || '?'}
                            </div>
                        </div>
                    ) : (
                        <p css={emptyStateText}>{t('server_amxx_web.query_no_response')}</p>
                    )}
                </TitledGreyBox>

                <TitledGreyBox title={t('server_amxx_web.amxx')}>
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
                                <span css={tw`text-neutral-400`}>{t('server_amxx_web.installed')}:</span>{' '}
                                {overview.amxx_installed ? t('server_amxx_web.yes') : t('server_amxx_web.no')}
                            </div>
                            <div>
                                <span css={tw`text-neutral-400`}>{t('server_amxx_web.users_ini')}:</span>{' '}
                                {overview.users_ini_exists
                                    ? t('server_amxx_web.found')
                                    : t('server_amxx_web.missing')}
                            </div>
                            <div>
                                <span css={tw`text-neutral-400`}>{t('server_amxx_web.directory')}:</span>{' '}
                                {overview.game_directory}
                            </div>
                            <div css={tw`flex flex-wrap gap-2 pt-2`}>
                                <Link to={`/server/${uuid}/amxx/admins`}>
                                    <Button size={Button.Sizes.Small}>{t('server_amxx_web.admins')}</Button>
                                </Link>
                                <Link to={`/server/${uuid}/amxx/bans`}>
                                    <Button size={Button.Sizes.Small}>{t('server_amxx_web.bans')}</Button>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <p css={emptyStateText}>{t('server_amxx_web.overview_load_failed')}</p>
                    )}
                </TitledGreyBox>

                <TitledGreyBox title={t('server_amxx_web.console_status')}>
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
                                <span css={tw`text-neutral-400`}>{t('server_amxx_web.console_players')}:</span>{' '}
                                {consolePlayers.length}
                            </div>
                            {playersData?.hostname && (
                                <div>
                                    <span css={tw`text-neutral-400`}>{t('server_amxx_web.hostname')}:</span>{' '}
                                    {playersData.hostname}
                                </div>
                            )}
                            {playersData?.map && (
                                <div>
                                    <span css={tw`text-neutral-400`}>{t('server_amxx_web.map')}:</span>{' '}
                                    {playersData.map}
                                </div>
                            )}
                            {playersData?.queried_at && (
                                <div css={tw`text-xs text-neutral-500`}>
                                    {t('server_amxx_web.updated_at')}:{' '}
                                    {new Date(playersData.queried_at).toLocaleString()}
                                </div>
                            )}
                        </div>
                    )}
                </TitledGreyBox>
            </div>

            <TitledGreyBox title={t('server_amxx_web.players_online')}>
                {playersOffline ? (
                    <p css={emptyStateText}>{runtimeStateMessage}</p>
                ) : playersLoading && displayPlayers.length === 0 && !playersRuntimeError && !usingQueryFallback ? (
                    <Spinner size={'large'} centered />
                ) : playersRuntimeError && displayPlayers.length === 0 ? (
                    <p css={emptyStateText}>{playersUnavailableMessage}</p>
                ) : playersLoadError && displayPlayers.length === 0 ? (
                    <p css={emptyStateText}>{playersLoadError}</p>
                ) : displayPlayers.length === 0 ? (
                    <p css={emptyStateText}>{t('server_amxx_web.no_players')}</p>
                ) : (
                    <>
                        {usingQueryFallback && (
                            <p css={[emptyStateText, tw`mb-3 text-yellow-200/80`]}>
                                {t('server_amxx_web.query_fallback_note')}
                            </p>
                        )}
                        <div css={tw`overflow-x-auto`}>
                            <table css={tw`w-full text-sm text-left text-neutral-200`}>
                                <thead>
                                    <tr css={tw`border-b border-neutral-700 text-neutral-400 uppercase text-xs`}>
                                        <th css={tw`py-2 pr-4`}>{t('server_amxx_web.col_id')}</th>
                                        <th css={tw`py-2 pr-4`}>{t('server_amxx_web.col_name')}</th>
                                        <th css={tw`py-2 pr-4`}>{t('server_amxx_web.col_steamid')}</th>
                                        <th css={tw`py-2 pr-4`}>{t('server_amxx_web.col_score')}</th>
                                        <th css={tw`py-2 pr-4`}>{t('server_amxx_web.col_ping')}</th>
                                        <th css={tw`py-2 pr-4`}>{t('server_amxx_web.col_connected')}</th>
                                        <th css={tw`py-2`}>{t('server_amxx_web.col_actions')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {displayPlayers.map((player) => (
                                        <tr
                                            key={`${player.source}-${player.userid}-${player.slot}-${player.name}`}
                                            css={tw`border-b border-neutral-800`}
                                        >
                                            <td css={tw`py-2 pr-4 font-mono`}>
                                                {player.userid > 0 ? `#${player.userid}` : '—'}
                                            </td>
                                            <td css={tw`py-2 pr-4`}>{player.name}</td>
                                            <td css={tw`py-2 pr-4 font-mono text-xs`}>{player.steamid}</td>
                                            <td css={tw`py-2 pr-4`}>{player.score}</td>
                                            <td css={tw`py-2 pr-4`}>{player.ping}</td>
                                            <td css={tw`py-2 pr-4 font-mono text-xs`}>{player.connected || '—'}</td>
                                            <td css={tw`py-2`}>
                                                <div css={tw`flex flex-wrap gap-2`}>
                                                    <Can action={['amxx.kick', 'control.console']} matchAny>
                                                        <Button
                                                            size={Button.Sizes.Small}
                                                            disabled={!canKickPlayer(player) || busyUserId === player.userid}
                                                            onClick={() => onKick(player)}
                                                        >
                                                            {t('server_amxx_web.kick')}
                                                        </Button>
                                                    </Can>
                                                    <Can action={['amxx.slay', 'control.console']} matchAny>
                                                        <Button
                                                            size={Button.Sizes.Small}
                                                            disabled={!canActionPlayer(player) || busyUserId === player.userid}
                                                            onClick={() => onSlap(player)}
                                                        >
                                                            {t('server_amxx_web.slap')}
                                                        </Button>
                                                        <Button
                                                            size={Button.Sizes.Small}
                                                            disabled={!canActionPlayer(player) || busyUserId === player.userid}
                                                            onClick={() => onSlay(player)}
                                                        >
                                                            {t('server_amxx_web.slay')}
                                                        </Button>
                                                    </Can>
                                                    <Can action={['amxx.ban', 'firewall.create']} matchAny>
                                                        <Button
                                                            size={Button.Sizes.Small}
                                                            disabled={!canBanPlayer(player) || busyUserId === player.userid}
                                                            onClick={() => openBanModal(player)}
                                                        >
                                                            {t('server_amxx_web.ban')}
                                                        </Button>
                                                    </Can>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </TitledGreyBox>

            {banTarget && (
                <TitledGreyBox title={t('server_amxx_web.ban_title', { name: banTarget.name })} css={tw`mt-4`}>
                    <div css={tw`grid gap-4 md:grid-cols-2`}>
                        {banTargetHasSteam && banTargetHasIp && (
                            <div>
                                <Label>{t('server_amxx_web.ban_type')}</Label>
                                <Select
                                    value={banType}
                                    onChange={(e) => setBanType(e.target.value as BanType)}
                                >
                                    <option value={'steamid'}>{t('server_amxx_web.steamid')}</option>
                                    <option value={'ip'}>{t('server_amxx_web.ip')}</option>
                                </Select>
                            </div>
                        )}
                        <div>
                            <Label>
                                {banType === 'ip' ? t('server_amxx_web.ip') : t('server_amxx_web.steamid')}
                            </Label>
                            <Input
                                value={banType === 'ip' ? (banTargetIp || '') : banTarget.steamid}
                                readOnly
                            />
                        </div>
                        <div>
                            <Label>{t('server_amxx_web.duration')}</Label>
                            <Select value={banMinutes} onChange={(e) => setBanMinutes(e.target.value)}>
                                {durationOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div css={tw`md:col-span-2`}>
                            <Label>{t('server_amxx_web.reason')}</Label>
                            <Input value={banReason} onChange={(e) => setBanReason(e.target.value)} />
                        </div>
                    </div>
                    <div css={tw`flex gap-2 mt-4`}>
                        <Button disabled={busyUserId === banTarget.userid} onClick={onBan}>
                            {t('server_amxx_web.confirm_ban')}
                        </Button>
                        <Button.Text onClick={() => setBanTarget(null)}>
                            {t('server_amxx_web.cancel')}
                        </Button.Text>
                    </div>
                </TitledGreyBox>
            )}

            <AmxxWebLivePanel
                uuid={uuid}
                isServerRunning={isServerRunning}
                currentMap={currentMap}
                consolePlayers={displayPlayers}
            />
        </ServerContentBlock>
    );
};
