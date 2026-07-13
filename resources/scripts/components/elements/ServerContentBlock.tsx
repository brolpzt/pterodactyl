import PageContentBlock, { PageContentBlockProps } from '@/components/elements/PageContentBlock';
import React from 'react';
import { ServerContext } from '@/state/server';
import { Alert } from '@/components/elements/alert';

interface Props extends PageContentBlockProps {
    title: string;
}

const ServerContentBlock: React.FC<Props> = ({ title, children, ...props }) => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isInstallRestricted = ServerContext.useStoreState((state) => state.server.isInstallRestricted);
    const installStatus = ServerContext.useStoreState((state) => state.server.data?.status);

    return (
        <PageContentBlock dense title={`${name} | ${title}`} {...props}>
            {isInstalling && (
                <Alert type={'warning'} className={'mb-4 text-sm py-2 px-3 [&>svg]:w-4 [&>svg]:h-4 [&>svg]:mr-1.5'}>
                    Aguarde a instalação. Arquivos e controles ficam disponíveis ao terminar.
                </Alert>
            )}
            {!isInstalling && isInstallRestricted && (
                <Alert type={'danger'} className={'mb-4 text-sm py-2 px-3 [&>svg]:w-4 [&>svg]:h-4 [&>svg]:mr-1.5'}>
                    {installStatus === 'reinstall_failed'
                        ? 'A reinstalação falhou. Verifique o console e tente novamente em Configurações.'
                        : 'A instalação falhou. Verifique o console e tente reinstalar em Configurações.'}
                </Alert>
            )}
            {children}
        </PageContentBlock>
    );
};

export default ServerContentBlock;
