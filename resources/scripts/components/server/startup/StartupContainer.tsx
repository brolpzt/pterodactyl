import React, { useEffect } from 'react';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import tw from 'twin.macro';
import VariableBox from '@/components/server/startup/VariableBox';
import getServerStartup from '@/api/swr/getServerStartup';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { ServerContext } from '@/state/server';
import { useDeepCompareEffect } from '@/plugins/useDeepCompareEffect';
import isEqual from 'react-fast-compare';

const STARTUP_COMMAND_DESCRIPTION =
    'Comando de inicialização gerado a partir das variáveis abaixo. Somente leitura.';

const StartupContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const variables = ServerContext.useStoreState(
        ({ server }) => ({
            variables: server.data!.variables,
            invocation: server.data!.invocation,
            dockerImage: server.data!.dockerImage,
        }),
        isEqual
    );

    const { data, error, isValidating, mutate } = getServerStartup(uuid, {
        ...variables,
        dockerImages: { [variables.dockerImage]: variables.dockerImage },
    });

    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);

    useEffect(() => {
        mutate();
    }, []);

    useDeepCompareEffect(() => {
        if (!data) return;

        setServerFromState((s) => ({
            ...s,
            invocation: data.invocation,
            variables: data.variables,
        }));
    }, [data]);

    return !data ? (
        !error || (error && isValidating) ? (
            <Spinner centered size={Spinner.Size.LARGE} />
        ) : (
            <ServerError title={'Oops!'} message={httpErrorToHuman(error)} onRetry={() => mutate()} />
        )
    ) : (
        <div css={tw`mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 items-start`}>
            <TitledGreyBox title={'Startup Command'}>
                <div css={tw`px-1 py-2`}>
                    <p className={'mono-panel'}>{data.invocation}</p>
                    <p css={tw`mt-2 text-xs text-[var(--hg-nav-text)]`}>{STARTUP_COMMAND_DESCRIPTION}</p>
                </div>
            </TitledGreyBox>

            <TitledGreyBox title={'Variables'}>
                {data.variables.map((variable) => (
                    <VariableBox key={variable.envVariable} variable={variable} />
                ))}
            </TitledGreyBox>
        </div>
    );
};

export default StartupContainer;
