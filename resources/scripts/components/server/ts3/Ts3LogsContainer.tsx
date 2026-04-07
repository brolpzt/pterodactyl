import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import { Button } from '@/components/elements/button/index';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import getLogs, { Ts3LogEntry } from '@/api/server/ts3/getLogs';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [logs, setLogs] = useState<Ts3LogEntry[]>([]);
    const [lines, setLines] = useState('100');
    const [loading, setLoading] = useState(true);
    const { clearAndAddHttpError, clearFlashes } = useFlash();

    const load = async () => {
        setLoading(true);
        clearFlashes('ts3:logs');
        try {
            const value = Number(lines) || 100;
            setLogs(await getLogs(uuid, value));
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:logs', error: err });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, [uuid]);

    return (
        <ServerContentBlock title={'TS3 Logs'}>
            <FlashMessageRender byKey={'ts3:logs'} css={tw`mb-4`} />

            <TitledGreyBox title={'Consulta de logs'} css={tw`mb-6`}>
                <div css={tw`flex items-end gap-3`}>
                    <div>
                        <Label>Linhas</Label>
                        <Input value={lines} onChange={(e) => setLines(e.currentTarget.value)} css={tw`w-28`} />
                    </div>
                    <Button onClick={load} disabled={loading}>
                        {loading ? <Spinner size={Spinner.Size.SMALL} /> : 'Atualizar'}
                    </Button>
                </div>
            </TitledGreyBox>

            <TitledGreyBox title={'Resultado'}>
                {loading ? (
                    <Spinner centered size={Spinner.Size.LARGE} />
                ) : logs.length === 0 ? (
                    <p css={tw`text-sm text-neutral-400 text-center py-4`}>Nenhum log retornado.</p>
                ) : (
                    <pre css={tw`bg-neutral-900 rounded p-3 text-xs overflow-auto max-h-[28rem]`}>
                        {logs.map((line, index) => line.l || JSON.stringify(line)).join('\n')}
                    </pre>
                )}
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
