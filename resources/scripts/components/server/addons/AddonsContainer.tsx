import React, { useState } from 'react';
import { ServerContext } from '@/state/server';
import getServerAddons, { Addon } from '@/api/swr/getServerAddons';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import tw from 'twin.macro';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Button from '@/components/elements/Button';
import useFlash from '@/plugins/useFlash';
import installAddon from '@/api/server/installAddon';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPuzzlePiece, faDownload } from '@fortawesome/free-solid-svg-icons';

const AddonBox = ({ addon }: { addon: Addon }) => {
    const [loading, setLoading] = useState(false);
    const { clearFlashes, addFlash, clearAndAddHttpError } = useFlash();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    const onInstall = () => {
        setLoading(true);
        clearFlashes('server:addons');
        installAddon(uuid, addon.id)
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
        <TitledGreyBox
            title={
                <div css={tw`flex items-center`}>
                    <FontAwesomeIcon icon={faPuzzlePiece} css={tw`mr-2 text-neutral-300`} />
                    <span>{addon.name}</span>
                </div>
            }
        >
            <div css={tw`px-1`}>
                <p css={tw`text-sm text-neutral-200 mb-4`}>{addon.description}</p>
                <div css={tw`flex items-center justify-end mt-auto`}>
                    <Button size={'xsmall'} onClick={onInstall} isLoading={loading}>
                        <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                        Install
                    </Button>
                </div>
            </div>
        </TitledGreyBox>
    );
};

import FlashMessageRender from '@/components/FlashMessageRender';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { data, error, isValidating, mutate } = getServerAddons(uuid);

    return !data ? (
        !error || (error && isValidating) ? (
            <Spinner centered size={Spinner.Size.LARGE} />
        ) : (
            <ServerError title={'Oops!'} message={httpErrorToHuman(error)} onRetry={() => mutate()} />
        )
    ) : (
        <ServerContentBlock title={'Server Addons'}>
            <FlashMessageRender byKey={'server:addons'} css={tw`mb-4`} />
            <div css={tw`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4`}>
                {data.length > 0 ? (
                    data.map((addon) => <AddonBox key={addon.id} addon={addon} />)
                ) : (
                    <p css={tw`text-center text-neutral-400 col-span-full`}>No addons available for this server type.</p>
                )}
            </div>
        </ServerContentBlock>
    );
};

