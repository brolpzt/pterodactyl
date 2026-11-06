import React from 'react';
import tw from 'twin.macro';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { LightningBoltIcon } from '@heroicons/react/solid';
import { ServerContext } from '@/state/server';
import GreyRowBox from '@/components/elements/GreyRowBox';
import Can from '@/components/elements/Can';
import { Button } from '@/components/elements/button/index';

/**
 * Points users with many or large files to SFTP, which is much faster than
 * uploading and downloading through the browser.
 */
export default () => {
    const { t } = useTranslation('strings');
    const id = ServerContext.useStoreState((state) => state.server.data!.id);

    return (
        <Can action={'file.sftp'}>
            <GreyRowBox $compact $hoverable={false} css={tw`mb-4 flex-wrap gap-x-4 gap-y-2 py-2`}>
                <div className={'flex min-w-0 flex-1 items-center gap-2'}>
                    <LightningBoltIcon className={'h-5 w-5 flex-shrink-0'} style={{ color: 'var(--color-primary)' }} />
                    <span style={{ color: 'var(--color-text-muted)' }}>{t('files_sftp.hint')}</span>
                </div>
                <Link to={`/server/${id}/settings`}>
                    <Button variant={Button.Variants.Secondary} size={Button.Sizes.Small}>
                        {t('files_sftp.button')}
                    </Button>
                </Link>
            </GreyRowBox>
        </Can>
    );
};
