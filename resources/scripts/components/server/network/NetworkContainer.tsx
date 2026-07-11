import React, { useEffect, useState } from 'react';
import { Link, useRouteMatch } from 'react-router-dom';
import Spinner from '@/components/elements/Spinner';
import { useFlashKey } from '@/plugins/useFlash';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { ServerContext } from '@/state/server';
import AllocationRow from '@/components/server/network/AllocationRow';
import { Button } from '@/components/elements/button/index';
import createServerAllocation from '@/api/server/network/createServerAllocation';
import tw from 'twin.macro';
import { emptyStateText, navText } from '@/assets/css/cardTheme';
import Can from '@/components/elements/Can';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import getServerAllocations from '@/api/swr/getServerAllocations';
import isEqual from 'react-fast-compare';
import { useDeepCompareEffect } from '@/plugins/useDeepCompareEffect';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faGlobe } from '@fortawesome/free-solid-svg-icons';
import { ip } from '@/lib/formatters';

const NetworkContainer = () => {
    const [loading, setLoading] = useState(false);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures);
    const allocationLimit = ServerContext.useStoreState((state) => state.server.data!.featureLimits.allocations);
    const allocations = ServerContext.useStoreState((state) => state.server.data!.allocations, isEqual);
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);
    const match = useRouteMatch<{ id: string }>('/server/:id');
    const hasDnsFeature = eggFeatures.includes('dns');
    const primaryAllocation = allocations.find((allocation) => allocation.isDefault);

    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:network');
    const { data, error, mutate } = getServerAllocations();

    useEffect(() => {
        mutate(allocations);
    }, []);

    useEffect(() => {
        clearAndAddHttpError(error);
    }, [error]);

    useDeepCompareEffect(() => {
        if (!data) return;

        setServerFromState((state) => ({ ...state, allocations: data }));
    }, [data]);

    const onCreateAllocation = () => {
        clearFlashes();

        setLoading(true);
        createServerAllocation(uuid)
            .then((allocation) => {
                setServerFromState((s) => ({ ...s, allocations: s.allocations.concat(allocation) }));
                return mutate(data?.concat(allocation), false);
            })
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setLoading(false));
    };

    return (
        <ServerContentBlock showFlashKey={'server:network'} title={'Network'}>
            {!data ? (
                <Spinner size={'large'} centered />
            ) : (
                <>
                    {hasDnsFeature && primaryAllocation && (
                        <Can action={'dns.create'}>
                            <TitledGreyBox
                                title={
                                    <div css={tw`flex items-center`}>
                                        <FontAwesomeIcon icon={faGlobe} css={tw`mr-2`} />
                                        <span css={tw`text-sm uppercase`}>DNS Cloudflare</span>
                                    </div>
                                }
                                css={tw`mb-4`}
                            >
                                <p css={[navText, tw`text-sm mb-3`]}>
                                    A alocação primária atual é{' '}
                                    <span css={tw`font-mono text-neutral-200`}>
                                        {primaryAllocation.alias || ip(primaryAllocation.ip)}:{primaryAllocation.port}
                                    </span>
                                    . Registros DNS do tipo A ou SRV usam estes dados automaticamente.
                                </p>
                                {match && (
                                    <Link to={`/server/${match.params.id}/dns`}>
                                        <Button>
                                            <FontAwesomeIcon icon={faGlobe} css={tw`mr-2`} />
                                            Criar registro DNS
                                        </Button>
                                    </Link>
                                )}
                            </TitledGreyBox>
                        </Can>
                    )}
                    {data.map((allocation) => (
                        <AllocationRow key={`${allocation.ip}:${allocation.port}`} allocation={allocation} />
                    ))}
                    {allocationLimit > 0 && (
                        <Can action={'allocation.create'}>
                            <SpinnerOverlay visible={loading} />
                            <div css={tw`mt-6 sm:flex items-center justify-end`}>
                                <p css={[navText, tw`text-sm mb-4 sm:mr-6 sm:mb-0`]}>
                                    You are currently using {data.length} of {allocationLimit} allowed allocations for
                                    this server.
                                </p>
                                {allocationLimit > data.length && (
                                    <Button
                                        className={'w-full sm:w-auto'}
                                        variant={Button.Variants.Secondary}
                                        onClick={onCreateAllocation}
                                    >
                                        Create Allocation
                                    </Button>
                                )}
                            </div>
                        </Can>
                    )}
                </>
            )}
        </ServerContentBlock>
    );
};

export default NetworkContainer;
