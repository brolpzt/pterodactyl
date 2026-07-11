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
import getMaps from '@/api/server/amxx/getMaps';
import changeMap from '@/api/server/amxx/changeMap';
import sendSay from '@/api/server/amxx/sendSay';
import sendPsay from '@/api/server/amxx/sendPsay';
import { AmxxConsolePlayer, AmxxMap } from '@/api/server/amxx/types';
import { getCs16MapImageUrl } from '@/lib/cs16MapImage';

type ConsolePlayerOption = Pick<AmxxConsolePlayer, 'userid' | 'name'>;

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
    const [mapPreviewFailed, setMapPreviewFailed] = useState(false);

    const mapPreviewUrl = useMemo(
        () => (selectedMap ? getCs16MapImageUrl(selectedMap) : null),
        [selectedMap]
    );

    const [sayMessage, setSayMessage] = useState('');
    const [psayMessage, setPsayMessage] = useState('');
    const [psayUserId, setPsayUserId] = useState('');
    const [chatBusy, setChatBusy] = useState<'say' | 'psay' | null>(null);

    const psayOptions = useMemo(
        () => consolePlayers.filter((player) => player.userid > 0),
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

    useEffect(() => {
        loadMaps();
    }, [uuid]);

    useEffect(() => {
        if (!currentMap || maps.length === 0) {
            return;
        }

        const preferred = maps.find((map) => map.name.toLowerCase() === currentMap.toLowerCase());
        if (preferred) {
            setSelectedMap(preferred.name);
        }
    }, [currentMap, maps]);

    useEffect(() => {
        setMapPreviewFailed(false);
    }, [selectedMap]);

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

    const showMapPreview = Boolean(mapPreviewUrl) && !mapPreviewFailed;

    return (
        <div css={tw`grid gap-4 mb-4 lg:grid-cols-2`}>
            <Can action={['amxx.map', 'control.console']} matchAny>
                <TitledGreyBox title={t('server_amxx_web.map_title')}>
                    {mapsLoading && maps.length === 0 ? (
                        <Spinner size={'small'} centered />
                    ) : maps.length === 0 ? (
                        <p css={emptyStateText}>{t('server_amxx_web.map_empty')}</p>
                    ) : (
                        <div css={tw`space-y-3`}>
                            {selectedMap && (
                                <div
                                    css={tw`relative overflow-hidden rounded-lg border border-neutral-700 bg-neutral-900/70`}
                                >
                                    {showMapPreview ? (
                                        <img
                                            src={mapPreviewUrl!}
                                            alt={t('server_amxx_web.map_preview_alt', { map: selectedMap })}
                                            css={tw`w-full h-36 object-cover`}
                                            onError={() => setMapPreviewFailed(true)}
                                        />
                                    ) : (
                                        <div css={[emptyStateText, tw`flex items-center justify-center h-36 px-4 text-center`]}>
                                            {t('server_amxx_web.map_preview_missing')}
                                        </div>
                                    )}
                                    <div
                                        css={tw`absolute inset-x-0 bottom-0 px-3 py-2 text-sm font-semibold text-white bg-gradient-to-t from-black/80 to-transparent`}
                                    >
                                        {selectedMap}
                                    </div>
                                </div>
                            )}
                            {currentMap && currentMap.toLowerCase() !== selectedMap.toLowerCase() && (
                                <div css={tw`text-sm text-neutral-200`}>
                                    <span css={tw`text-neutral-400`}>{t('server_amxx_web.map_current')}:</span>{' '}
                                    {currentMap}
                                </div>
                            )}
                            <div>
                                <Label>{t('server_amxx_web.map_select')}</Label>
                                <Select value={selectedMap} onChange={(e) => setSelectedMap(e.target.value)}>
                                    {maps.map((map) => (
                                        <option key={map.name} value={map.name}>
                                            {map.name}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <Button size={Button.Sizes.Small} disabled={mapBusy || !selectedMap} onClick={onChangeMap}>
                                {mapBusy ? <Spinner size={'small'} /> : t('server_amxx_web.map_change')}
                            </Button>
                        </div>
                    )}
                </TitledGreyBox>
            </Can>

            <Can action={['amxx.chat', 'control.console']} matchAny>
                <TitledGreyBox title={t('server_amxx_web.chat_title')}>
                    <div css={tw`space-y-4`}>
                            <div>
                                <Label>{t('server_amxx_web.chat_say')}</Label>
                                <Input value={sayMessage} onChange={(e) => setSayMessage(e.target.value)} />
                                <Button
                                    size={Button.Sizes.Small}
                                    css={tw`mt-2`}
                                    disabled={chatBusy !== null || !sayMessage.trim()}
                                    onClick={onSay}
                                >
                                    {chatBusy === 'say' ? <Spinner size={'small'} /> : t('server_amxx_web.chat_send')}
                                </Button>
                            </div>
                            <div>
                                <Label>{t('server_amxx_web.chat_psay')}</Label>
                                <Select value={psayUserId} onChange={(e) => setPsayUserId(e.target.value)}>
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
                                    size={Button.Sizes.Small}
                                    css={tw`mt-2`}
                                    disabled={chatBusy !== null || !psayMessage.trim() || !psayUserId}
                                    onClick={onPsay}
                                >
                                    {chatBusy === 'psay' ? <Spinner size={'small'} /> : t('server_amxx_web.chat_send')}
                                </Button>
                            </div>
                    </div>
                </TitledGreyBox>
            </Can>
        </div>
    );
};
