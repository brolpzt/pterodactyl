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
import getSnapshots from '@/api/server/ts3/getSnapshots';
import createSnapshot from '@/api/server/ts3/createSnapshot';
import restoreSnapshot from '@/api/server/ts3/restoreSnapshot';
import deleteSnapshot from '@/api/server/ts3/deleteSnapshot';
import { Ts3Snapshot } from '@/api/server/ts3/types';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [snapshots, setSnapshots] = useState<Ts3Snapshot[]>([]);
    const [name, setName] = useState('');
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState<string | null>(null);
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const load = async () => {
        setLoading(true);
        try {
            setSnapshots(await getSnapshots(uuid));
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:snapshots', error: err });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, [uuid]);

    const onCreate = async () => {
        clearFlashes('ts3:snapshots');
        setBusy('create');
        try {
            await createSnapshot(uuid, name || undefined);
            setName('');
            addFlash({ key: 'ts3:snapshots', type: 'success', message: 'Snapshot criado com sucesso.' });
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:snapshots', error: err });
        } finally {
            setBusy(null);
        }
    };

    const onRestore = async (snapshotUuid: string) => {
        clearFlashes('ts3:snapshots');
        setBusy(`restore:${snapshotUuid}`);
        try {
            await restoreSnapshot(uuid, snapshotUuid);
            addFlash({ key: 'ts3:snapshots', type: 'success', message: 'Snapshot restaurado com sucesso.' });
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:snapshots', error: err });
        } finally {
            setBusy(null);
        }
    };

    const onDelete = async (snapshotUuid: string) => {
        clearFlashes('ts3:snapshots');
        setBusy(`delete:${snapshotUuid}`);
        try {
            await deleteSnapshot(uuid, snapshotUuid);
            addFlash({ key: 'ts3:snapshots', type: 'success', message: 'Snapshot removido com sucesso.' });
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'ts3:snapshots', error: err });
        } finally {
            setBusy(null);
        }
    };

    return (
        <ServerContentBlock title={'TS3 Snapshots'}>
            <FlashMessageRender byKey={'ts3:snapshots'} css={tw`mb-4`} />

            <TitledGreyBox title={'Criar snapshot'} css={tw`mb-6`}>
                <div css={tw`flex items-end gap-3`}>
                    <div css={tw`flex-1`}>
                        <Label>Nome</Label>
                        <Input value={name} onChange={(e) => setName(e.currentTarget.value)} placeholder={'Snapshot manual'} />
                    </div>
                    <Button onClick={onCreate} disabled={busy !== null}>
                        {busy === 'create' ? <Spinner size={Spinner.Size.SMALL} /> : 'Criar'}
                    </Button>
                </div>
            </TitledGreyBox>

            <TitledGreyBox title={'Snapshots salvos'}>
                {loading ? (
                    <Spinner centered size={Spinner.Size.LARGE} />
                ) : snapshots.length === 0 ? (
                    <p css={tw`text-sm text-neutral-400 text-center py-4`}>Nenhum snapshot salvo.</p>
                ) : (
                    <table css={tw`w-full text-sm`}>
                        <thead>
                            <tr css={tw`text-neutral-400 border-b border-neutral-600`}>
                                <th css={tw`text-left py-2`}>Nome</th>
                                <th css={tw`text-left py-2`}>Criado em</th>
                                <th css={tw`text-right py-2`}>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {snapshots.map((snapshot) => (
                                <tr key={snapshot.uuid} css={tw`border-b border-neutral-700`}>
                                    <td css={tw`py-2`}>{snapshot.name}</td>
                                    <td css={tw`py-2`}>{new Date(snapshot.created_at).toLocaleString('pt-BR')}</td>
                                    <td css={tw`py-2 text-right space-x-2`}>
                                        <Button
                                            disabled={busy !== null}
                                            onClick={() => onRestore(snapshot.uuid)}
                                        >
                                            {busy === `restore:${snapshot.uuid}` ? <Spinner size={Spinner.Size.SMALL} /> : 'Restaurar'}
                                        </Button>
                                        <Button.Danger
                                            disabled={busy !== null}
                                            onClick={() => onDelete(snapshot.uuid)}
                                        >
                                            {busy === `delete:${snapshot.uuid}` ? <Spinner size={Spinner.Size.SMALL} /> : 'Excluir'}
                                        </Button.Danger>
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
