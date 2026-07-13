import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import Label from '@/components/elements/Label';
import Select from '@/components/elements/Select';
import { Button } from '@/components/elements/button/index';
import Can from '@/components/elements/Can';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import useWebRconPlayers from '@/api/swr/getWebRconPlayers';
import kickPlayer from '@/api/server/webrcon/kickPlayer';
import banPlayer from '@/api/server/webrcon/banPlayer';
import WebRconToolbar from '@/components/server/webrcon/WebRconToolbar';
import WebRconLivePanel from '@/components/server/webrcon/WebRconLivePanel';
import { WebRconPlayer } from '@/api/server/webrcon/types';
import MessageBox from '@/components/MessageBox';

const parsePlayerIp = (address?: string): string | null => {
    if (!address) {
        return null;
    }

    const host = address.split(':')[0];
    return /^\d{1,3}(\.\d{1,3}){3}$/.test(host) ? host : null;
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
    const status = ServerContext.useStoreState((state) => state.status.value);
    const isServerRunning = status === 'running';

    const {
        data: playersData,
        error: playersError,
        isLoading: playersLoading,
        mutate: refreshPlayers,
    } = useWebRconPlayers(isServerRunning);

    const [busyClientnum, setBusyClientnum] = useState<number | null>(null);
    const [refreshing, setRefreshing] = useState(false);
    const [banTarget, setBanTarget] = useState<WebRconPlayer | null>(null);
    const [banMinutes, setBanMinutes] = useState('0');
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const durationOptions = useMemo(
        () => [
            { value: '0', label: t('server_webrcon.duration_permanent') },
            { value: '30', label: t('server_webrcon.duration_30m') },
            { value: '60', label: t('server_webrcon.duration_1h') },
            { value: '1440', label: t('server_webrcon.duration_24h') },
            { value: '10080', label: t('server_webrcon.duration_7d') },
        ],
        [t]
    );

    const runtimeStateMessage = useMemo(() => {
        switch (status) {
            case 'starting':
                return t('server_webrcon.starting_screen');
            case 'stopping':
                return t('server_webrcon.stopping_screen');
            case 'offline':
            default:
                return t('server_webrcon.offline_screen');
        }
    }, [status, t]);

    const runtimeStateTitle = useMemo(() => {
        switch (status) {
            case 'starting':
                return t('server_webrcon.starting_title');
            case 'stopping':
                return t('server_webrcon.stopping_title');
            case 'offline':
            default:
                return t('server_webrcon.offline_title');
        }
    }, [status, t]);

    useEffect(() => {
        if (!isServerRunning) {
            setBanTarget(null);
        }
    }, [isServerRunning]);

    const displayPlayers = playersData?.players ?? [];
    const currentMap = playersData?.map ?? null;

    const onRefresh = async () => {
        if (!isServerRunning) {
            return;
        }

        setRefreshing(true);
        try {
            await refreshPlayers();
        } finally {
            setRefreshing(false);
        }
    };

    const onKick = async (player: WebRconPlayer) => {
        clearFlashes('webrcon:web');
        setBusyClientnum(player.clientnum);
        try {
            await kickPlayer(uuid, player.clientnum, t('server_webrcon.kick_reason'));
            addFlash({
                key: 'webrcon:web',
                type: 'success',
                message: t('server_webrcon.kick_success', { name: player.name }),
            });
            await refreshPlayers();
        } catch (err) {
            clearAndAddHttpError({ key: 'webrcon:web', error: err });
        } finally {
            setBusyClientnum(null);
        }
    };

    const onBan = async () => {
        if (!banTarget) {
            return;
        }

        clearFlashes('webrcon:web');
        setBusyClientnum(banTarget.clientnum);
        try {
            await banPlayer(
                uuid,
                banTarget.clientnum,
                parseInt(banMinutes, 10) || 0,
                banTarget.guid,
            );
            addFlash({
                key: 'webrcon:web',
                type: 'success',
                message: t('server_webrcon.ban_success', { name: banTarget.name }),
            });
            setBanTarget(null);
            await refreshPlayers();
        } catch (err) {
            clearAndAddHttpError({ key: 'webrcon:web', error: err });
        } finally {
            setBusyClientnum(null);
        }
    };

    const playersUnavailableMessage = t('server_webrcon.players_unavailable');
    const playersRuntimeError = !isServerRunning || isPlayersUnavailableError(playersError);
    const playersLoadError = playersError && !playersRuntimeError ? httpErrorToHuman(playersError) : null;

    return (
        <ServerContentBlock title={t('server_webrcon.title')}>
            <FlashMessageRender byKey={'webrcon:web'} css={tw`mb-4`} />

            {!isServerRunning ? (
                <MessageBox type={'info'} title={runtimeStateTitle}>
                    {runtimeStateMessage}
                </MessageBox>
            ) : (
                <>
                    <div css={tw`flex justify-end mb-4`}>
                        <Button
                            size={Button.Sizes.Small}
                            disabled={refreshing}
                            onClick={onRefresh}
                        >
                            {refreshing ? <Spinner size={'small'} /> : t('server_webrcon.refresh')}
                        </Button>
                    </div>

                    <WebRconToolbar
                        uuid={uuid}
                        isServerRunning={isServerRunning}
                        currentMap={currentMap}
                        consolePlayers={displayPlayers}
                    />

                    <TitledGreyBox title={t('server_webrcon.players_online')}>
                        {playersLoading && displayPlayers.length === 0 && !playersRuntimeError ? (
                            <Spinner size={'large'} centered />
                        ) : playersRuntimeError && displayPlayers.length === 0 ? (
                            <p css={emptyStateText}>{playersUnavailableMessage}</p>
                        ) : playersLoadError && displayPlayers.length === 0 ? (
                            <p css={emptyStateText}>{playersLoadError}</p>
                        ) : displayPlayers.length === 0 ? (
                            <p css={emptyStateText}>{t('server_webrcon.no_players')}</p>
                        ) : (
                            <div css={tw`overflow-x-auto`}>
                                <table css={tw`w-full text-sm text-left text-neutral-200`}>
                                    <thead>
                                        <tr css={tw`border-b border-neutral-700 text-neutral-400 uppercase text-xs`}>
                                            <th css={tw`py-2 pr-4`}>{t('server_webrcon.col_id')}</th>
                                            <th css={tw`py-2 pr-4`}>{t('server_webrcon.col_name')}</th>
                                            <th css={tw`py-2 pr-4`}>{t('server_webrcon.col_guid')}</th>
                                            <th css={tw`py-2 pr-4`}>{t('server_webrcon.col_score')}</th>
                                            <th css={tw`py-2 pr-4`}>{t('server_webrcon.col_ping')}</th>
                                            <th css={tw`py-2 pr-4`}>{t('server_webrcon.col_address')}</th>
                                            <th css={tw`py-2`}>{t('server_webrcon.col_actions')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {displayPlayers.map((player) => (
                                            <tr
                                                key={`${player.clientnum}-${player.name}`}
                                                css={tw`border-b border-neutral-800`}
                                            >
                                                <td css={tw`py-2 pr-4 font-mono`}>#{player.clientnum}</td>
                                                <td css={tw`py-2 pr-4`}>{player.name}</td>
                                                <td css={tw`py-2 pr-4 font-mono text-xs`}>
                                                    {player.guid || '—'}
                                                </td>
                                                <td css={tw`py-2 pr-4`}>{player.score}</td>
                                                <td css={tw`py-2 pr-4`}>{player.ping}</td>
                                                <td css={tw`py-2 pr-4 font-mono text-xs`}>
                                                    {parsePlayerIp(player.address) || player.address || '—'}
                                                </td>
                                                <td css={tw`py-2`}>
                                                    <div css={tw`flex flex-wrap gap-2`}>
                                                        <Can action={['webrcon.kick', 'control.console']} matchAny>
                                                            <Button
                                                                size={Button.Sizes.Small}
                                                                disabled={busyClientnum === player.clientnum}
                                                                onClick={() => onKick(player)}
                                                            >
                                                                {t('server_webrcon.kick')}
                                                            </Button>
                                                        </Can>
                                                        <Can action={['webrcon.ban', 'firewall.create']} matchAny>
                                                            <Button
                                                                size={Button.Sizes.Small}
                                                                disabled={busyClientnum === player.clientnum}
                                                                onClick={() => {
                                                                    setBanTarget(player);
                                                                    setBanMinutes('0');
                                                                }}
                                                            >
                                                                {t('server_webrcon.ban')}
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
                        <TitledGreyBox
                            title={t('server_webrcon.ban_title', { name: banTarget.name })}
                            css={tw`mt-4`}
                        >
                            <div css={tw`grid gap-4 md:grid-cols-2`}>
                                <div>
                                    <Label>{t('server_webrcon.duration')}</Label>
                                    <Select value={banMinutes} onChange={(e) => setBanMinutes(e.target.value)}>
                                        {durationOptions.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <div css={tw`flex items-end gap-2`}>
                                    <Button
                                        size={Button.Sizes.Small}
                                        disabled={busyClientnum === banTarget.clientnum}
                                        onClick={onBan}
                                    >
                                        {t('server_webrcon.confirm_ban')}
                                    </Button>
                                    <Button
                                        size={Button.Sizes.Small}
                                        variant={Button.Variants.Secondary}
                                        onClick={() => setBanTarget(null)}
                                    >
                                        {t('server_webrcon.cancel')}
                                    </Button>
                                </div>
                            </div>
                        </TitledGreyBox>
                    )}

                    <WebRconLivePanel uuid={uuid} isServerRunning={isServerRunning} />
                </>
            )}
        </ServerContentBlock>
    );
};
