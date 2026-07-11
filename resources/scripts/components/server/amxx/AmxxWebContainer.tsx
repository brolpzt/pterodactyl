import React, { useEffect, useMemo, useState } from 'react';
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
import useAmxxPlayers from '@/api/swr/getAmxxPlayers';
import kickPlayer from '@/api/server/amxx/kickPlayer';
import slapPlayer from '@/api/server/amxx/slapPlayer';
import slayPlayer from '@/api/server/amxx/slayPlayer';
import createBan from '@/api/server/amxx/createBan';
import createAdmin from '@/api/server/amxx/createAdmin';
import AmxxWebToolbar from '@/components/server/amxx/AmxxWebToolbar';
import AmxxWebLivePanel from '@/components/server/amxx/AmxxWebLivePanel';
import { AMXX_PRESET_FLAGS, AmxxConsolePlayer, AmxxPreset } from '@/api/server/amxx/types';

type DisplayPlayer = AmxxConsolePlayer;
type BanType = 'steamid' | 'ip';
type QuickAdminPreset = Extract<AmxxPreset, 'mod' | 'admin'>;

const QUICK_ADMIN_PRESETS: QuickAdminPreset[] = ['mod', 'admin'];

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
    const status = ServerContext.useStoreState((state) => state.status.value);
    const isServerRunning = status === 'running';

    const {
        data: playersData,
        error: playersError,
        isLoading: playersLoading,
        mutate: refreshPlayers,
    } = useAmxxPlayers(isServerRunning);

    const [busyUserId, setBusyUserId] = useState<number | null>(null);
    const [refreshing, setRefreshing] = useState(false);
    const [banTarget, setBanTarget] = useState<AmxxConsolePlayer | null>(null);
    const [adminTarget, setAdminTarget] = useState<DisplayPlayer | null>(null);
    const [adminPreset, setAdminPreset] = useState<QuickAdminPreset>('mod');
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
        if (!isServerRunning) {
            setBanTarget(null);
            setAdminTarget(null);
        }
    }, [isServerRunning]);

    const consolePlayers = playersData?.players ?? [];
    const displayPlayers = consolePlayers;

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

    const openAdminModal = (player: DisplayPlayer) => {
        setAdminTarget(player);
        setAdminPreset('mod');
    };

    const onMakeAdmin = async () => {
        if (!adminTarget || !isValidSteamId(adminTarget.steamid)) {
            return;
        }

        clearFlashes('amxx:web');
        setBusyUserId(adminTarget.userid);
        try {
            await createAdmin(uuid, {
                auth_type: 'steamid',
                auth: adminTarget.steamid,
                preset: adminPreset,
                access_flags: AMXX_PRESET_FLAGS[adminPreset],
                nickname: adminTarget.name,
                reload: true,
            });
            addFlash({
                key: 'amxx:web',
                type: 'success',
                message: t('server_amxx_web.make_admin_success', { name: adminTarget.name }),
            });
            setAdminTarget(null);
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setBusyUserId(null);
        }
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
    const canActionPlayer = (player: DisplayPlayer) => player.userid > 0;
    const canKickPlayer = (player: DisplayPlayer) => canActionPlayer(player);
    const canBanPlayer = (player: DisplayPlayer) =>
        isValidSteamId(player.steamid) || Boolean(parsePlayerIp(player.address));
    const canMakeAdminPlayer = (player: DisplayPlayer) =>
        canActionPlayer(player) && isValidSteamId(player.steamid);
    const currentMap = playersData?.map || null;

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

            <AmxxWebToolbar
                uuid={uuid}
                isServerRunning={isServerRunning}
                currentMap={currentMap}
                consolePlayers={displayPlayers}
            />

            <TitledGreyBox title={t('server_amxx_web.players_online')}>
                {playersOffline ? (
                    <p css={emptyStateText}>{runtimeStateMessage}</p>
                ) : playersLoading && displayPlayers.length === 0 && !playersRuntimeError ? (
                    <Spinner size={'large'} centered />
                ) : playersRuntimeError && displayPlayers.length === 0 ? (
                    <p css={emptyStateText}>{playersUnavailableMessage}</p>
                ) : playersLoadError && displayPlayers.length === 0 ? (
                    <p css={emptyStateText}>{playersLoadError}</p>
                ) : displayPlayers.length === 0 ? (
                    <p css={emptyStateText}>{t('server_amxx_web.no_players')}</p>
                ) : (
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
                                            key={`${player.userid}-${player.slot}-${player.name}`}
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
                                                    <Can action={'file.update'}>
                                                        <Button
                                                            size={Button.Sizes.Small}
                                                            disabled={
                                                                !canMakeAdminPlayer(player) || busyUserId === player.userid
                                                            }
                                                            onClick={() => openAdminModal(player)}
                                                        >
                                                            {t('server_amxx_web.make_admin')}
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

            {adminTarget && (
                <TitledGreyBox title={t('server_amxx_web.make_admin_title', { name: adminTarget.name })} css={tw`mt-4`}>
                    <div css={tw`grid gap-4 md:grid-cols-2`}>
                        <div>
                            <Label>{t('server_amxx_web.steamid')}</Label>
                            <Input value={adminTarget.steamid} readOnly />
                        </div>
                        <div>
                            <Label>{t('server_amxx_web.make_admin_preset')}</Label>
                            <Select
                                value={adminPreset}
                                onChange={(e) => setAdminPreset(e.target.value as QuickAdminPreset)}
                            >
                                {QUICK_ADMIN_PRESETS.map((preset) => (
                                    <option key={preset} value={preset}>
                                        {t(`server_amxx_web.make_admin_preset_${preset}`)}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    </div>
                    <div css={tw`flex gap-2 mt-4`}>
                        <Button disabled={busyUserId === adminTarget.userid} onClick={onMakeAdmin}>
                            {t('server_amxx_web.make_admin_confirm')}
                        </Button>
                        <Button.Text onClick={() => setAdminTarget(null)}>
                            {t('server_amxx_web.cancel')}
                        </Button.Text>
                    </div>
                </TitledGreyBox>
            )}

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

            <AmxxWebLivePanel uuid={uuid} isServerRunning={isServerRunning} />
        </ServerContentBlock>
    );
};
