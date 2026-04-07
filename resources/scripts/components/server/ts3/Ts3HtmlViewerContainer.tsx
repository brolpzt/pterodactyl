import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import getHtmlViewer from '@/api/server/ts3/getHtmlViewer';
import { Ts3HtmlViewer } from '@/api/server/ts3/types';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [data, setData] = useState<Ts3HtmlViewer | null>(null);
    const [loading, setLoading] = useState(true);
    const { clearAndAddHttpError } = useFlash();

    useEffect(() => {
        setLoading(true);
        getHtmlViewer(uuid)
            .then(setData)
            .catch((err) => clearAndAddHttpError({ key: 'ts3:html', error: err }))
            .finally(() => setLoading(false));
    }, [uuid]);

    const channels = data?.channels ?? [];
    const connectedClients = (data?.clients ?? []).filter((client) => client.client_type !== '1');

    const channelsByParent = channels.reduce<Record<string, typeof channels>>((acc, channel) => {
        const parentId = channel.pid ?? '0';
        acc[parentId] = acc[parentId] || [];
        acc[parentId].push(channel);
        return acc;
    }, {});

    const clientsByChannel = connectedClients.reduce<Record<string, typeof connectedClients>>((acc, client) => {
        const channelId = client.cid ?? '';
        if (!channelId) return acc;
        acc[channelId] = acc[channelId] || [];
        acc[channelId].push(client);
        return acc;
    }, {});

    const renderChannelTree = (parentId: string, depth = 0): React.ReactNode => {
        const children = channelsByParent[parentId] || [];
        if (children.length === 0) return null;

        return children.map((channel) => {
            const channelId = channel.cid ?? '';
            const levelPadding = `${depth * 1.25}rem`;
            const channelClients = clientsByChannel[channelId] || [];

            return (
                <div key={channelId || `${parentId}-${channel.channel_name}`}>
                    <div css={tw`text-sm text-neutral-100 py-1`} style={{ paddingLeft: levelPadding }}>
                        <span css={tw`font-medium`}># {channel.channel_name || 'Canal sem nome'}</span>
                        <span css={tw`text-neutral-500 ml-2`}>
                            ({channelClients.length} online)
                        </span>
                    </div>

                    {channelClients.map((client) => (
                        <div
                            key={client.clid || `${channelId}-${client.client_nickname}`}
                            css={tw`text-xs text-neutral-300 py-0.5`}
                            style={{ paddingLeft: `${depth * 1.25 + 1.25}rem` }}
                        >
                            • {client.client_nickname || 'Usuário sem nickname'}
                            {client.client_away === '1' && <span css={tw`text-neutral-500 ml-2`}>(away)</span>}
                        </div>
                    ))}

                    {renderChannelTree(channelId, depth + 1)}
                </div>
            );
        });
    };

    return (
        <ServerContentBlock title={'TS3 HTML Viewer'}>
            <FlashMessageRender byKey={'ts3:html'} css={tw`mb-4`} />
            <TitledGreyBox title={'TS3 Viewer (Canais e Usuários)'}>
                {loading ? (
                    <Spinner centered size={Spinner.Size.LARGE} />
                ) : (
                    <div css={tw`space-y-3`}>
                        <p css={tw`text-sm text-neutral-300`}>
                            {data?.server_name || 'Servidor TS3'} - {connectedClients.length} usuários conectados.
                        </p>
                        <div css={tw`bg-neutral-900/70 border border-neutral-700 rounded p-3 max-h-[34rem] overflow-auto`}>
                            {channels.length === 0 ? (
                                <p css={tw`text-sm text-neutral-400`}>Nenhum canal encontrado.</p>
                            ) : (
                                renderChannelTree('0')
                            )}
                        </div>
                    </div>
                )}
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
