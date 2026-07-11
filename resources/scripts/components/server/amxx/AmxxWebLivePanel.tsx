import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { Button } from '@/components/elements/button/index';
import Can from '@/components/elements/Can';
import useFlash from '@/plugins/useFlash';
import { useActivityLogs } from '@/api/server/activity';
import ActivityLogEntry from '@/components/elements/activity/ActivityLogEntry';
import ActivityLogList from '@/components/elements/activity/ActivityLogList';
import getMaps from '@/api/server/amxx/getMaps';
import changeMap from '@/api/server/amxx/changeMap';
import sendSay from '@/api/server/amxx/sendSay';
import sendPsay from '@/api/server/amxx/sendPsay';
import getCvars from '@/api/server/amxx/getCvars';
import queryCvar from '@/api/server/amxx/queryCvar';
import setCvar from '@/api/server/amxx/setCvar';
import { AmxxConsolePlayer, AmxxCvar, AmxxMap } from '@/api/server/amxx/types';

type ConsolePlayerOption = Pick<AmxxConsolePlayer, 'userid' | 'name'> & { source: 'console' | 'query' };

interface Props {
    uuid: string;
    isServerRunning: boolean;
    currentMap?: string | null;
    consolePlayers: ConsolePlayerOption[];
}

export default ({ uuid, isServerRunning, currentMap, consolePlayers }: Props) => {
    const { t } = useTranslation('strings');
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const [maps, setMaps] = useState<AmxxMap[]>([]);
    const [mapsLoading, setMapsLoading] = useState(false);
    const [selectedMap, setSelectedMap] = useState('');
    const [mapBusy, setMapBusy] = useState(false);

    const [sayMessage, setSayMessage] = useState('');
    const [psayMessage, setPsayMessage] = useState('');
    const [psayUserId, setPsayUserId] = useState('');
    const [chatBusy, setChatBusy] = useState<'say' | 'psay' | null>(null);

    const [cvars, setCvars] = useState<AmxxCvar[]>([]);
    const [cvarsLoading, setCvarsLoading] = useState(false);
    const [cvarDrafts, setCvarDrafts] = useState<Record<string, string>>({});
    const [cvarBusy, setCvarBusy] = useState<string | null>(null);

    const activityFilters = useMemo(
        () => ({ page: 1, sorts: { timestamp: -1 as const }, filters: { event: 'server:amxx' } }),
        []
    );
    const { data: activityData, isValidating: activityLoading } = useActivityLogs(activityFilters, {
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    const psayOptions = useMemo(
        () => consolePlayers.filter((player) => player.source === 'console' && player.userid > 0),
        [consolePlayers]
    );

    const loadMaps = async () => {
        setMapsLoading(true);
        try {
            const data = await getMaps(uuid);
            setMaps(data);
            if (!selectedMap && data.length > 0) {
                const preferred = currentMap
                    ? data.find((map) => map.name.toLowerCase() === currentMap.toLowerCase())
                    : null;
                setSelectedMap(preferred?.name || data[0].name);
            }
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setMapsLoading(false);
        }
    };

    const loadCvars = async () => {
        if (!isServerRunning) {
            return;
        }

        setCvarsLoading(true);
        try {
            const definitions = await getCvars(uuid);
            setCvars(definitions);
            setCvarDrafts(
                definitions.reduce<Record<string, string>>((acc, cvar) => {
                    acc[cvar.name] = cvar.value ?? '';
                    return acc;
                }, {})
            );

            const queried = await Promise.all(
                definitions.map(async (definition) => {
                    try {
                        return await queryCvar(uuid, definition.name);
                    } catch {
                        return definition;
                    }
                })
            );

            setCvars(queried);
            setCvarDrafts(
                queried.reduce<Record<string, string>>((acc, cvar) => {
                    acc[cvar.name] = cvar.value ?? '';
                    return acc;
                }, {})
            );
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setCvarsLoading(false);
        }
    };

    useEffect(() => {
        loadMaps();
    }, [uuid]);

    useEffect(() => {
        if (isServerRunning) {
            loadCvars();
        }
    }, [uuid, isServerRunning]);

    const onChangeMap = async () => {
        if (!selectedMap) {
            return;
        }

        clearFlashes('amxx:web');
        setMapBusy(true);
        try {
            await changeMap(uuid, selectedMap);
            addFlash({
                key: 'amxx:web',
                type: 'success',
                message: t('server_amxx_web.map_success', { map: selectedMap }),
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setMapBusy(false);
        }
    };

    const onSay = async () => {
        if (!sayMessage.trim()) {
            return;
        }

        clearFlashes('amxx:web');
        setChatBusy('say');
        try {
            await sendSay(uuid, sayMessage.trim());
            setSayMessage('');
            addFlash({ key: 'amxx:web', type: 'success', message: t('server_amxx_web.chat_say_success') });
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setChatBusy(null);
        }
    };

    const onPsay = async () => {
        const userid = parseInt(psayUserId, 10);
        if (!psayMessage.trim() || !userid) {
            return;
        }

        const target = psayOptions.find((player) => player.userid === userid);
        clearFlashes('amxx:web');
        setChatBusy('psay');
        try {
            await sendPsay(uuid, userid, psayMessage.trim());
            setPsayMessage('');
            addFlash({
                key: 'amxx:web',
                type: 'success',
                message: t('server_amxx_web.chat_psay_success', { name: target?.name || `#${userid}` }),
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setChatBusy(null);
        }
    };

    const onSaveCvar = async (name: string) => {
        const value = cvarDrafts[name];
        if (value === undefined) {
            return;
        }

        clearFlashes('amxx:web');
        setCvarBusy(name);
        try {
            await setCvar(uuid, name, value);
            addFlash({
                key: 'amxx:web',
                type: 'success',
                message: t('server_amxx_web.cvars_success', { name, value }),
            });
            await loadCvars();
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setCvarBusy(null);
        }
    };

    return (
        <div css={tw`space-y-4 mt-4`}>
            <div css={tw`grid gap-4 lg:grid-cols-2`}>
                <Can action={['amxx.map', 'control.console']} matchAny>
                    <TitledGreyBox title={t('server_amxx_web.map_title')}>
                        {!isServerRunning ? (
                            <p css={emptyStateText}>{t('server_amxx_web.offline')}</p>
                        ) : mapsLoading && maps.length === 0 ? (
                            <Spinner size={'small'} centered />
                        ) : maps.length === 0 ? (
                            <p css={emptyStateText}>{t('server_amxx_web.map_empty')}</p>
                        ) : (
                            <div css={tw`space-y-3`}>
                                {currentMap && (
                                    <div css={tw`text-sm text-neutral-200`}>
                                        <span css={tw`text-neutral-400`}>{t('server_amxx_web.map_current')}:</span>{' '}
                                        {currentMap}
                                    </div>
                                )}
                                <div>
                                    <Label>{t('server_amxx_web.map_select')}</Label>
                                    <Select
                                        value={selectedMap}
                                        onChange={(e) => setSelectedMap(e.target.value)}
                                    >
                                        {maps.map((map) => (
                                            <option key={map.name} value={map.name}>
                                                {map.name}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <Button disabled={mapBusy || !selectedMap} onClick={onChangeMap}>
                                    {mapBusy ? <Spinner size={'small'} /> : t('server_amxx_web.map_change')}
                                </Button>
                            </div>
                        )}
                    </TitledGreyBox>
                </Can>

                <Can action={['amxx.chat', 'control.console']} matchAny>
                    <TitledGreyBox title={t('server_amxx_web.chat_title')}>
                        {!isServerRunning ? (
                            <p css={emptyStateText}>{t('server_amxx_web.offline')}</p>
                        ) : (
                            <div css={tw`space-y-4`}>
                                <div>
                                    <Label>{t('server_amxx_web.chat_say')}</Label>
                                    <Input
                                        value={sayMessage}
                                        onChange={(e) => setSayMessage(e.target.value)}
                                    />
                                    <Button
                                        css={tw`mt-2`}
                                        disabled={chatBusy !== null || !sayMessage.trim()}
                                        onClick={onSay}
                                    >
                                        {chatBusy === 'say' ? <Spinner size={'small'} /> : t('server_amxx_web.chat_send')}
                                    </Button>
                                </div>
                                <div>
                                    <Label>{t('server_amxx_web.chat_psay')}</Label>
                                    <Select
                                        value={psayUserId}
                                        onChange={(e) => setPsayUserId(e.target.value)}
                                    >
                                        <option value={''}>{t('server_amxx_web.select_player')}</option>
                                        {psayOptions.map((player) => (
                                            <option key={player.userid} value={String(player.userid)}>
                                                #{player.userid} — {player.name}
                                            </option>
                                        ))}
                                    </Select>
                                    <Input
                                        css={tw`mt-2`}
                                        value={psayMessage}
                                        onChange={(e) => setPsayMessage(e.target.value)}
                                    />
                                    <Button
                                        css={tw`mt-2`}
                                        disabled={chatBusy !== null || !psayMessage.trim() || !psayUserId}
                                        onClick={onPsay}
                                    >
                                        {chatBusy === 'psay' ? <Spinner size={'small'} /> : t('server_amxx_web.chat_send')}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </TitledGreyBox>
                </Can>
            </div>

            <Can action={['amxx.cvar', 'control.console']} matchAny>
                <TitledGreyBox title={t('server_amxx_web.cvars_title')}>
                    {!isServerRunning ? (
                        <p css={emptyStateText}>{t('server_amxx_web.offline')}</p>
                    ) : cvarsLoading && cvars.length === 0 ? (
                        <Spinner size={'small'} centered />
                    ) : (
                        <>
                            <div css={tw`flex justify-end mb-3`}>
                                <Button.Text disabled={cvarsLoading} onClick={loadCvars}>
                                    {t('server_amxx_web.cvars_refresh')}
                                </Button.Text>
                            </div>
                            <div css={tw`overflow-x-auto`}>
                                <table css={tw`w-full text-sm text-left text-neutral-200`}>
                                    <thead>
                                        <tr css={tw`border-b border-neutral-700 text-neutral-400 uppercase text-xs`}>
                                            <th css={tw`py-2 pr-4`}>{t('server_amxx_web.cvars_name')}</th>
                                            <th css={tw`py-2 pr-4`}>{t('server_amxx_web.cvars_value')}</th>
                                            <th css={tw`py-2`} />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cvars.map((cvar) => (
                                            <tr key={cvar.name} css={tw`border-b border-neutral-800`}>
                                                <td css={tw`py-2 pr-4`}>
                                                    <div css={tw`font-mono text-xs`}>{cvar.name}</div>
                                                    <div css={tw`text-xs text-neutral-500`}>{cvar.label}</div>
                                                </td>
                                                <td css={tw`py-2 pr-4`}>
                                                    <Input
                                                        value={cvarDrafts[cvar.name] ?? ''}
                                                        onChange={(e) =>
                                                            setCvarDrafts((current) => ({
                                                                ...current,
                                                                [cvar.name]: e.target.value,
                                                            }))
                                                        }
                                                    />
                                                </td>
                                                <td css={tw`py-2`}>
                                                    <Button
                                                        size={Button.Sizes.Small}
                                                        disabled={cvarBusy === cvar.name}
                                                        onClick={() => onSaveCvar(cvar.name)}
                                                    >
                                                        {cvarBusy === cvar.name
                                                            ? <Spinner size={'small'} />
                                                            : t('server_amxx_web.cvars_save')}
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </TitledGreyBox>
            </Can>

            <Can action={['amxx.read', 'activity.read', 'control.console']} matchAny>
                <TitledGreyBox title={t('server_amxx_web.activity_title')}>
                    {activityLoading && !activityData ? (
                        <Spinner size={'small'} centered />
                    ) : !activityData?.items.length ? (
                        <p css={emptyStateText}>{t('server_amxx_web.activity_empty')}</p>
                    ) : (
                        <ActivityLogList>
                            {activityData.items.map((activity) => (
                                <ActivityLogEntry key={activity.id} activity={activity}>
                                    <span />
                                </ActivityLogEntry>
                            ))}
                        </ActivityLogList>
                    )}
                </TitledGreyBox>
            </Can>
        </div>
    );
};
