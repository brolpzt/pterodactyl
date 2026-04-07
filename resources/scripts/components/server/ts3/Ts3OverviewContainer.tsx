import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Button } from '@/components/elements/button';
import { ServerContext } from '@/state/server';
import getOverview from '@/api/server/ts3/getOverview';
import postAction, { Ts3Action } from '@/api/server/ts3/postAction';
import { Ts3Overview } from '@/api/server/ts3/types';

const actionLabels: Record<Ts3Action, string> = {
    start: 'Start',
    stop: 'Stop',
    restart: 'Restart',
    reinstall: 'Reinstall',
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [data, setData] = useState<Ts3Overview | null>(null);
    const [error, setError] = useState<any>(null);
    const [loading, setLoading] = useState(true);
    const [action, setAction] = useState<Ts3Action | null>(null);
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();

    const load = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await getOverview(uuid);
            setData(response);
        } catch (err) {
            setError(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, [uuid]);

    const onAction = async (value: Ts3Action) => {
        clearFlashes('ts3:overview');
        setAction(value);
        try {
            await postAction(uuid, value);
            addFlash({
                key: 'ts3:overview',
                type: 'success',
                message: `Ação TS3 "${actionLabels[value]}" enviada com sucesso.`,
            });
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:overview', error: err });
        } finally {
            setAction(null);
        }
    };

    if (loading && !data) {
        return <Spinner centered size={Spinner.Size.LARGE} />;
    }

    if (!data && error) {
        return <ServerError title={'Erro ao carregar TS3'} message={httpErrorToHuman(error)} onRetry={load} />;
    }

    return (
        <ServerContentBlock title={'TS3 Control'}>
            <FlashMessageRender byKey={'ts3:overview'} css={tw`mb-4`} />

            <TitledGreyBox title={'Informações do servidor TS3'} css={tw`mb-6`}>
                <table css={tw`w-full text-left text-sm`}>
                    <tbody>
                        <tr css={tw`border-b border-neutral-600`}><td css={tw`py-2 text-neutral-400`}>Status</td><td>{data?.status || '—'}</td></tr>
                        <tr css={tw`border-b border-neutral-600`}><td css={tw`py-2 text-neutral-400`}>Version</td><td>{data?.version || '—'} {data?.build ? `(Build ${data.build})` : ''}</td></tr>
                        <tr css={tw`border-b border-neutral-600`}><td css={tw`py-2 text-neutral-400`}>Platform</td><td>{data?.platform || '—'}</td></tr>
                        <tr css={tw`border-b border-neutral-600`}><td css={tw`py-2 text-neutral-400`}>Server name</td><td>{data?.server_name || '—'}</td></tr>
                        <tr css={tw`border-b border-neutral-600`}><td css={tw`py-2 text-neutral-400`}>Uptime (s)</td><td>{data?.uptime_seconds ?? '—'}</td></tr>
                        <tr css={tw`border-b border-neutral-600`}><td css={tw`py-2 text-neutral-400`}>Clients online</td><td>{data?.clients_online ?? '—'}</td></tr>
                        <tr><td css={tw`py-2 text-neutral-400`}>Channels online</td><td>{data?.channels_online ?? '—'}</td></tr>
                    </tbody>
                </table>
            </TitledGreyBox>

            <TitledGreyBox title={'Service management'}>
                <div css={tw`grid grid-cols-2 md:grid-cols-4 gap-3`}>
                    {(['start', 'stop', 'restart', 'reinstall'] as Ts3Action[]).map((item) => (
                        <Button key={item} disabled={action !== null} onClick={() => onAction(item)}>
                            {action === item ? <Spinner size={Spinner.Size.SMALL} /> : actionLabels[item]}
                        </Button>
                    ))}
                </div>
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
