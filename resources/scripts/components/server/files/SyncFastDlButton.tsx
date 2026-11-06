import React, { useState } from 'react';
import { ServerContext } from '@/state/server';
import { Button } from '@/components/elements/button/index';
import useFlash from '@/plugins/useFlash';
import syncFastDl from '@/api/server/fastdl/syncFastDl';
import { WithClassname } from '@/components/types';
import { useTranslation } from 'react-i18next';
import useFastDlStatus from '@/api/swr/getFastDlStatus';

export default ({ className }: WithClassname) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const fastdlEnabled = ServerContext.useStoreState((state) => state.server.data!.fastdlEnabled);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const { t } = useTranslation('strings');
    const { data: status, mutate } = useFastDlStatus(fastdlEnabled);
    const running = status?.sync?.state === 'running';

    if (!fastdlEnabled) {
        return null;
    }

    const onSubmit = () => {
        setIsSubmitting(true);
        clearFlashes('files');

        syncFastDl(uuid)
            .then(() => {
                addFlash({ key: 'files', type: 'success', message: t('fastdl.queued') });
            })
            .catch((error) => {
                clearAndAddHttpError({ key: 'files', error });
            })
            .then(() => {
                setIsSubmitting(false);
                // Wings starts the sync in the background; pick up the "running" state.
                setTimeout(() => mutate(), 800);
            });
    };

    return (
        <Button
            variant={Button.Variants.Secondary}
            size={Button.Sizes.Small}
            disabled={isSubmitting || running}
            onClick={onSubmit}
            className={className}
        >
            {t('fastdl.sync_button')}
        </Button>
    );
};
