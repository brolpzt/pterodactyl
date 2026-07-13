import React from 'react';
import tw from 'twin.macro';
import { useTranslation } from 'react-i18next';
import ContentContainer from '@/components/elements/ContentContainer';
import { Alert } from '@/components/elements/alert';
import { ServerContext } from '@/state/server';
import useGameQuery from '@/api/swr/getGameQuery';
import { supportsGameQuery } from '@/lib/supportsGameQuery';
import { stripGameHostnameColors } from '@/lib/stripGameHostnameColors';

const HostnameBrandingAlert = () => {
    const { t } = useTranslation('strings');
    const gamedig = ServerContext.useStoreState((state) => state.server.data?.gamedig);
    const eggId = ServerContext.useStoreState((state) => state.server.data?.eggId);
    const queryEnabled = supportsGameQuery(gamedig, eggId);
    const { data: query } = useGameQuery(queryEnabled);

    if (!query?.branding?.show_warning) {
        return null;
    }

    const hostname = stripGameHostnameColors(query.branding.hostname || query.hostname) || '—';

    return (
        <ContentContainer css={tw`w-full min-w-0 mt-4 mb-4`}>
            <Alert type={'danger'} className={'text-sm py-2 px-3 [&>svg]:w-4 [&>svg]:h-4 [&>svg]:mr-1.5'}>
                {t('server.hostname_branding_warning', { hostname })}
            </Alert>
        </ContentContainer>
    );
};

export default HostnameBrandingAlert;
