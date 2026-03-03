import React, { useState } from 'react';
import { ServerContext } from '@/state/server';
import { Button } from '@/components/elements/button/index';
import useFlash from '@/plugins/useFlash';
import syncFastDl from '@/api/server/fastdl/syncFastDl';
import { WithClassname } from '@/components/types';

export default ({ className }: WithClassname) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const fastdlEnabled = ServerContext.useStoreState((state) => state.server.data!.fastdlEnabled);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();

    if (!fastdlEnabled) {
        return null;
    }

    const onSubmit = () => {
        setIsSubmitting(true);
        clearFlashes('files');

        syncFastDl(uuid)
            .then(() => {
                addFlash({
                    key: 'files',
                    type: 'success',
                    message: 'FastDL synchronization has been queued and will run in the background.',
                });
            })
            .catch((error) => {
                clearAndAddHttpError({ key: 'files', error });
            })
            .then(() => setIsSubmitting(false));
    };

    return (
        <Button.Text disabled={isSubmitting} onClick={onSubmit} className={className}>
            Sync FastDL
        </Button.Text>
    );
};
