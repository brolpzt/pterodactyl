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
import getMaps from '@/api/server/webrcon/getMaps';
import changeMap from '@/api/server/webrcon/changeMap';
import sendSay from '@/api/server/webrcon/sendSay';
import sendTell from '@/api/server/webrcon/sendTell';
import { WebRconMap, WebRconPlayer } from '@/api/server/webrcon/types';

interface Props {
    uuid: string;
    isServerRunning: boolean;
    currentMap?: string | null;
    consolePlayers: WebRconPlayer[];
}

export default ({ uuid, isServerRunning, currentMap, consolePlayers }: Props) => {
    const { t } = useTranslation('strings');
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const [maps, setMaps] = useState<WebRconMap[]>([]);
    const [mapsLoading, setMapsLoading] = useState(false);
    const [selectedMap, setSelectedMap] = useState('');
    const [mapBusy, setMapBusy] = useState(false);

    const [sayMessage, setSayMessage] = useState('');
    const [tellMessage, setTellMessage] = useState('');
    const [tellClientnum, setTellClientnum] = useState('');
    const [chatBusy, setChatBusy] = useState<'say' | 'tell' | null>(null);

    const tellOptions = useMemo(
        () => consolePlayers.filter((player) => player.clientnum >= 0),
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
            clearAndAddHttpError({ key: 'webrcon:web', error: err });
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

    const onChangeMap = async () => {
        if (!selectedMap) {
            return;
        }

        clearFlashes('webrcon:web');
        setMapBusy(true);
        try {
            await changeMap(uuid, selectedMap);
            addFlash({
                key: 'webrcon:web',
                type: 'success',
                message: t('server_webrcon.map_success', { map: selectedMap }),
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'webrcon:web', error: err });
        } finally {
            setMapBusy(false);
        }
    };

    const onSay = async () => {
        if (!sayMessage.trim()) {
            return;
        }

        clearFlashes('webrcon:web');
        setChatBusy('say');
        try {
            await sendSay(uuid, sayMessage.trim());
            setSayMessage('');
            addFlash({ key: 'webrcon:web', type: 'success', message: t('server_webrcon.chat_say_success') });
        } catch (err) {
            clearAndAddHttpError({ key: 'webrcon:web', error: err });
        } finally {
            setChatBusy(null);
        }
    };

    const onTell = async () => {
        const clientnum = parseInt(tellClientnum, 10);
        if (!tellMessage.trim() || Number.isNaN(clientnum)) {
            return;
        }

        const target = tellOptions.find((player) => player.clientnum === clientnum);
        clearFlashes('webrcon:web');
        setChatBusy('tell');
        try {
            await sendTell(uuid, clientnum, tellMessage.trim());
            setTellMessage('');
            addFlash({
                key: 'webrcon:web',
                type: 'success',
                message: t('server_webrcon.chat_tell_success', { name: target?.name || `#${clientnum}` }),
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'webrcon:web', error: err });
        } finally {
            setChatBusy(null);
        }
    };

    return (
        <div css={tw`grid gap-4 mb-4 lg:grid-cols-2`}>
            <Can action={['webrcon.map', 'control.console']} matchAny>
                <TitledGreyBox title={t('server_webrcon.map_title')}>
                    {mapsLoading && maps.length === 0 ? (
                        <Spinner size={'small'} centered />
                    ) : maps.length === 0 ? (
                        <p css={emptyStateText}>{t('server_webrcon.map_empty')}</p>
                    ) : (
                        <div css={tw`space-y-3`}>
                            {currentMap && (
                                <div css={tw`text-sm text-neutral-200`}>
                                    <span css={tw`text-neutral-400`}>{t('server_webrcon.map_current')}:</span>{' '}
                                    {currentMap}
                                </div>
                            )}
                            <div>
                                <Label>{t('server_webrcon.map_select')}</Label>
                                <Select value={selectedMap} onChange={(e) => setSelectedMap(e.target.value)}>
                                    {maps.map((map) => (
                                        <option key={map.name} value={map.name}>
                                            {map.name}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <Button size={Button.Sizes.Small} disabled={mapBusy || !selectedMap} onClick={onChangeMap}>
                                {mapBusy ? <Spinner size={'small'} /> : t('server_webrcon.map_change')}
                            </Button>
                        </div>
                    )}
                </TitledGreyBox>
            </Can>

            <Can action={['webrcon.chat', 'control.console']} matchAny>
                <TitledGreyBox title={t('server_webrcon.chat_title')}>
                    <div css={tw`space-y-4`}>
                        <div>
                            <Label>{t('server_webrcon.chat_say')}</Label>
                            <Input value={sayMessage} onChange={(e) => setSayMessage(e.target.value)} />
                            <Button
                                size={Button.Sizes.Small}
                                css={tw`mt-2`}
                                disabled={chatBusy !== null || !sayMessage.trim()}
                                onClick={onSay}
                            >
                                {chatBusy === 'say' ? <Spinner size={'small'} /> : t('server_webrcon.chat_send')}
                            </Button>
                        </div>
                        <div>
                            <Label>{t('server_webrcon.chat_tell')}</Label>
                            <Select value={tellClientnum} onChange={(e) => setTellClientnum(e.target.value)}>
                                <option value={''}>{t('server_webrcon.select_player')}</option>
                                {tellOptions.map((player) => (
                                    <option key={player.clientnum} value={String(player.clientnum)}>
                                        #{player.clientnum} — {player.name}
                                    </option>
                                ))}
                            </Select>
                            <Input
                                css={tw`mt-2`}
                                value={tellMessage}
                                onChange={(e) => setTellMessage(e.target.value)}
                            />
                            <Button
                                size={Button.Sizes.Small}
                                css={tw`mt-2`}
                                disabled={chatBusy !== null || !tellMessage.trim() || !tellClientnum}
                                onClick={onTell}
                            >
                                {chatBusy === 'tell' ? <Spinner size={'small'} /> : t('server_webrcon.chat_send')}
                            </Button>
                        </div>
                    </div>
                </TitledGreyBox>
            </Can>
        </div>
    );
};
