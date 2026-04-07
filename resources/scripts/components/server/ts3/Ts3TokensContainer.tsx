import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import { Button } from '@/components/elements/button/index';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import getTokens from '@/api/server/ts3/getTokens';
import createToken from '@/api/server/ts3/createToken';
import deleteToken from '@/api/server/ts3/deleteToken';
import { Ts3Token } from '@/api/server/ts3/types';
import CopyOnClick from '@/components/elements/CopyOnClick';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [tokens, setTokens] = useState<Ts3Token[]>([]);
    const [loading, setLoading] = useState(true);
    const [description, setDescription] = useState('');
    const [busy, setBusy] = useState<string | null>(null);
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const load = async () => {
        setLoading(true);
        try {
            setTokens(await getTokens(uuid));
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:tokens', error: err });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, [uuid]);

    const onCreate = async () => {
        clearFlashes('ts3:tokens');
        setBusy('create');
        try {
            const token = await createToken(uuid, description || undefined);
            setDescription('');
            addFlash({
                key: 'ts3:tokens',
                type: 'success',
                message: token ? `Token criado: ${token}` : 'Token criado com sucesso.',
            });
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:tokens', error: err });
        } finally {
            setBusy(null);
        }
    };

    const onDelete = async (token: string) => {
        clearFlashes('ts3:tokens');
        setBusy(token);
        try {
            await deleteToken(uuid, token);
            addFlash({ key: 'ts3:tokens', type: 'success', message: 'Token removido com sucesso.' });
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:tokens', error: err });
        } finally {
            setBusy(null);
        }
    };

    return (
        <ServerContentBlock title={'TS3 Tokens'}>
            <FlashMessageRender byKey={'ts3:tokens'} css={tw`mb-4`} />

            <TitledGreyBox title={'Gerar token'} css={tw`mb-6`}>
                <div css={tw`grid grid-cols-1 md:grid-cols-3 gap-3`}>
                    <div css={tw`md:col-span-2`}>
                        <Label>Descrição</Label>
                        <Input value={description} onChange={(e) => setDescription(e.currentTarget.value)} placeholder={'Opcional'} />
                    </div>
                    <div css={tw`flex items-end`}>
                        <Button onClick={onCreate} disabled={busy !== null}>
                            {busy === 'create' ? <Spinner size={Spinner.Size.SMALL} /> : 'Gerar token'}
                        </Button>
                    </div>
                </div>
            </TitledGreyBox>

            <TitledGreyBox title={'Lista de tokens'}>
                {loading ? (
                    <Spinner size={Spinner.Size.LARGE} centered />
                ) : tokens.length === 0 ? (
                    <p css={tw`text-sm text-neutral-400 text-center py-4`}>Nenhum token encontrado.</p>
                ) : (
                    <table css={tw`w-full text-sm`}>
                        <thead>
                            <tr css={tw`text-neutral-400 border-b border-neutral-600`}>
                                <th css={tw`text-left py-2`}>Token</th>
                                <th css={tw`text-left py-2`}>Descrição</th>
                                <th css={tw`text-right py-2`}>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tokens.map((item, index) => (
                                <tr key={`${item.token || index}`} css={tw`border-b border-neutral-700`}>
                                    <td css={tw`py-2 font-mono`}>
                                        {item.token ? (
                                            <CopyOnClick text={item.token}>
                                                <span css={tw`cursor-pointer hover:text-neutral-100`}>{item.token}</span>
                                            </CopyOnClick>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td css={tw`py-2`}>{item.token_description || '—'}</td>
                                    <td css={tw`py-2 text-right`}>
                                        {item.token ? (
                                            <Button.Danger disabled={busy !== null} onClick={() => onDelete(item.token!)}>
                                                {busy === item.token ? <Spinner size={Spinner.Size.SMALL} /> : 'Remover'}
                                            </Button.Danger>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
