import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import { Button } from '@/components/elements/button/index';
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

    return (
        <ServerContentBlock title={'TS3 HTML Viewer'}>
            <FlashMessageRender byKey={'ts3:html'} css={tw`mb-4`} />
            <TitledGreyBox title={'HTML Viewer'}>
                {loading ? (
                    <Spinner centered size={Spinner.Size.LARGE} />
                ) : !data?.url ? (
                    <p css={tw`text-sm text-neutral-400`}>
                        URL do HTML Viewer não configurada para este servidor (variável `TS3_HTML_VIEWER_URL`).
                    </p>
                ) : (
                    <div css={tw`space-y-3`}>
                        <p css={tw`text-sm text-neutral-300`}>
                            Abra o visualizador HTML externo para ver detalhes adicionais do servidor TS3.
                        </p>
                        <a href={data.url} target={'_blank'} rel={'noreferrer'}>
                            <Button>Abrir HTML Viewer</Button>
                        </a>
                    </div>
                )}
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
