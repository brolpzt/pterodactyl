import React, { useState } from 'react';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import { useStoreState } from 'easy-peasy';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import FlashMessageRender from '@/components/FlashMessageRender';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';
import { Button } from '@/components/elements/button/index';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import executeQueryCommand, { Ts3QueryRow } from '@/api/server/ts3/executeQueryCommand';
import { ServerError } from '@/components/elements/ScreenBlock';

export default () => {
    const rootAdmin = useStoreState((state: any) => state.user.data!.rootAdmin);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [command, setCommand] = useState('');
    const [loading, setLoading] = useState(false);
    const [response, setResponse] = useState<Ts3QueryRow[] | null>(null);
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();

    if (!rootAdmin) {
        return <ServerError title={'Access Denied'} message={'Only root administrators can access TS3 Query Terminal.'} />;
    }

    const onExecute = async () => {
        if (!command.trim()) return;
        clearFlashes('ts3:query');
        setLoading(true);
        try {
            const result = await executeQueryCommand(uuid, command.trim());
            setResponse(result);
            addFlash({
                key: 'ts3:query',
                type: 'success',
                message: 'Comando executado com sucesso.',
            });
        } catch (error) {
            clearAndAddHttpError({ key: 'ts3:query', error });
        } finally {
            setLoading(false);
        }
    };

    return (
        <ServerContentBlock title={'TS3 Query Terminal'}>
            <FlashMessageRender byKey={'ts3:query'} css={tw`mb-4`} />

            <TitledGreyBox title={'Executar comando TS3 Query'} css={tw`mb-6`}>
                <div css={tw`space-y-3`}>
                    <div>
                        <Label>Command</Label>
                        <Input
                            value={command}
                            onChange={(e) => setCommand(e.currentTarget.value)}
                            placeholder={'Ex: clientlist -uid'}
                            css={tw`font-mono`}
                        />
                    </div>
                    <Button onClick={onExecute} disabled={loading || !command.trim()}>
                        {loading ? <Spinner size={Spinner.Size.SMALL} /> : 'Executar'}
                    </Button>
                </div>
            </TitledGreyBox>

            <TitledGreyBox title={'Retorno'}>
                {!response ? (
                    <p css={emptyStateText}>Nenhum comando executado ainda.</p>
                ) : response.length === 0 ? (
                    <p css={emptyStateText}>Comando executado sem linhas de retorno.</p>
                ) : (
                    <pre css={tw`bg-neutral-900 rounded p-3 text-xs overflow-auto max-h-[32rem]`}>
                        {JSON.stringify(response, null, 2)}
                    </pre>
                )}
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
