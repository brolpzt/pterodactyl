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

    return (
        <PageContentBlock dense title={`${name} | ${title}`} {...props}>
            {isInstalling && (
                <Alert type={'warning'} className={'mb-4'}>
                    Este servidor está em processo de instalação. Algumas ações podem ficar indisponíveis até a
                    conclusão.
                </Alert>
            )}
            {children}
        </PageContentBlock>
    );
};

export default ServerContentBlock;
