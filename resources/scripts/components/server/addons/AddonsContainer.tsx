import React, { useState } from 'react';
import { ServerContext } from '@/state/server';
import getServerAddons, { Addon } from '@/api/swr/getServerAddons';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import useFlash from '@/plugins/useFlash';
import installAddon from '@/api/server/installAddon';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faDownload } from '@fortawesome/free-solid-svg-icons';
import { Dialog } from '@/components/elements/dialog';
import FlashMessageRender from '@/components/FlashMessageRender';

const AddonRow = ({ addon }: { addon: Addon }) => {
    const [loading, setLoading] = useState(false);
    const [modalVisible, setModalVisible] = useState(false);
    const { clearFlashes, addFlash, clearAndAddHttpError } = useFlash();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

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

    const attemptInstall = () => {
        if (addon.reinstallServer) {
            setModalVisible(true);
        } else {
            doInstall();
        }
    };

    return (
        <React.Fragment>
            <Dialog.Confirm
                open={modalVisible}
                title={'Confirm server reinstallation'}
                confirm={'Yes, install addon'}
                onClose={() => setModalVisible(false)}
                onConfirmed={doInstall}
            >
                Atenção: Instalar este addon irá provocar a reinstalação de todo o seu servidor. Todos os processos serão interrompidos. Deseja continuar?
            </Dialog.Confirm>
            <tr css={tw`border-b border-neutral-600 last:border-b-0`}>
                <td css={tw`py-4 pr-4 align-top w-2/5`}>
                    <p css={tw`text-base font-semibold text-neutral-100`}>{addon.name}</p>
                </td>
                <td css={tw`py-4 align-top w-full`}>
                    <p css={tw`text-sm text-neutral-300`}>{addon.description}</p>
                </td>
                <td css={tw`py-4 pl-4 align-middle text-right`}>
                    <Button.Text onClick={attemptInstall} disabled={loading} css={tw`whitespace-nowrap`}>
                        <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                        Contratar / Instalar
                    </Button.Text>
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
                categories.map((categoryName) => {
                    const categoryAddons = data.filter((a) => a.categoryName === categoryName);

                    return (
                        <div key={categoryName} css={tw`mb-8`}>
                            <h2 css={tw`text-2xl mb-4 font-semibold text-neutral-100`}>{categoryName}</h2>
                            <div css={tw`bg-neutral-700/50 p-6 rounded-md shadow-sm border border-neutral-600`}>
                                <table css={tw`w-full`}>
                                    <tbody>
                                        {categoryAddons.map((addon) => (
                                            <AddonRow key={addon.id} addon={addon} />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    );
                })
            ) : (
                <p css={tw`text-center text-neutral-400 mt-8`}>No addons available for this server type.</p>
            )}
        </ServerContentBlock>
    );
};

