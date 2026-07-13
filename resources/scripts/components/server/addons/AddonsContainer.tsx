import React, { useState } from 'react';
import { ServerContext } from '@/state/server';
import getServerAddons, { Addon } from '@/api/swr/getServerAddons';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import { Button } from '@/components/elements/button/index';
import useFlash from '@/plugins/useFlash';
import installAddon from '@/api/server/installAddon';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faDownload } from '@fortawesome/free-solid-svg-icons';
import { Dialog } from '@/components/elements/dialog';
import FlashMessageRender from '@/components/FlashMessageRender';
import TitledGreyBox from '@/components/elements/TitledGreyBox';

const AddonRow = ({ addon }: { addon: Addon }) => {
    const [loading, setLoading] = useState(false);
    const [modalVisible, setModalVisible] = useState(false);
    const { clearFlashes, addFlash, clearAndAddHttpError } = useFlash();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);

    const doInstall = () => {
        setModalVisible(false);
        setLoading(true);
        clearFlashes('server:addons');
        installAddon(uuid, addon.uuid)
            .then(() => {
                addFlash({
                    key: 'server:addons',
                    type: 'success',
                    message: `${addon.name} installation has been triggered. Check console for progress.`,
                });
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'server:addons', error });
            })
            .then(() => setLoading(false));
    };

    return (
        <React.Fragment>
            <Dialog.Confirm
                open={modalVisible}
                title={'Confirmar Instalação'}
                confirm={'Sim, instalar'}
                onClose={() => setModalVisible(false)}
                onConfirmed={doInstall}
            >
                Atenção: O seu servidor será desligado para a instalação do addon <strong>{addon.name}</strong>. Deseja continuar?
                {addon.reinstallServer && (
                    <p css={tw`mt-3 text-red-400 text-sm font-semibold`}>
                        Este addon pode modificar arquivos existentes. Realize um backup antes de continuar.
                    </p>
                )}
            </Dialog.Confirm>
            <tr css={tw`border-b border-neutral-600 last:border-b-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                <td css={tw`px-2 py-3 w-1/4`}>
                    <p css={tw`text-sm font-medium text-neutral-100`}>{addon.name}</p>
                </td>
                <td css={tw`px-2 py-3 w-2/4`}>
                    <p css={tw`text-xs text-neutral-400`}>{addon.description || '—'}</p>
                </td>
                <td css={tw`px-2 py-3 w-1/4 text-right`}>
                    <Button
                        variant={Button.Variants.Secondary}
                        onClick={() => setModalVisible(true)}
                        disabled={loading || isInstalling}
                    >
                        <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                        Instalar
                    </Button>
                </td>
            </tr>
        </React.Fragment>
    );
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { data, error, isValidating, mutate } = getServerAddons(uuid);

    if (!data) {
        if (!error || (error && isValidating)) {
            return <Spinner centered size={Spinner.Size.LARGE} />;
        }
        return <ServerError title={'Oops!'} message={httpErrorToHuman(error)} onRetry={() => mutate()} />;
    }

    const categories = Array.from(new Set(data.map((a) => a.categoryName)));

    return (
        <ServerContentBlock title={'Server Addons'}>
            <FlashMessageRender byKey={'server:addons'} css={tw`mb-4`} />

            {data.length > 0 ? (
                <div css={tw`grid gap-8`}>
                    {categories.map((categoryName) => {
                        const categoryAddons = data.filter((a) => a.categoryName === categoryName);

                        return (
                            <TitledGreyBox
                                key={categoryName}
                                title={
                                    <div css={tw`flex items-center`}>
                                        <span css={tw`text-sm uppercase`}>{categoryName}</span>
                                    </div>
                                }
                            >
                                <table css={tw`w-full text-left`}>
                                    <thead>
                                        <tr css={tw`text-xs text-neutral-400 uppercase border-b border-neutral-600`}>
                                            <th css={tw`px-2 pb-2 font-semibold w-1/4`}>Addon</th>
                                            <th css={tw`px-2 pb-2 font-semibold w-2/4`}>Descrição</th>
                                            <th css={tw`px-2 pb-2 font-semibold text-right w-1/4`}>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {categoryAddons.map((addon) => (
                                            <AddonRow key={addon.id} addon={addon} />
                                        ))}
                                    </tbody>
                                </table>
                            </TitledGreyBox>
                        );
                    })}
                </div>
            ) : (
                <p css={[emptyStateText, tw`mt-8`]}>Nenhum addon disponível para este tipo de servidor.</p>
            )}
        </ServerContentBlock>
    );
};
