import TransferListener from '@/components/server/TransferListener';
import React, { useEffect, useState } from 'react';
import { NavLink, Route, Switch, useRouteMatch } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import TransitionRouter from '@/TransitionRouter';
import WebsocketHandler from '@/components/server/WebsocketHandler';
import { ServerContext } from '@/state/server';
import { CSSTransition } from 'react-transition-group';
import Can from '@/components/elements/Can';
import Spinner from '@/components/elements/Spinner';
import { NotFound, ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import { useStoreState } from 'easy-peasy';
import SubNavigation from '@/components/elements/SubNavigation';
import InstallListener from '@/components/server/InstallListener';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faExternalLinkAlt } from '@fortawesome/free-solid-svg-icons';
import { useLocation } from 'react-router';
import ConflictStateRenderer from '@/components/server/ConflictStateRenderer';
import PermissionRoute from '@/components/elements/PermissionRoute';
import routes from '@/routers/routes';

import Sidebar from '@/components/Sidebar';
import tw from 'twin.macro';

export default () => {
    const match = useRouteMatch<{ id: string }>();
    const location = useLocation();

    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [error, setError] = useState('');

    const name = ServerContext.useStoreState((state) => state.server.data?.name);
    const id = ServerContext.useStoreState((state) => state.server.data?.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const node = ServerContext.useStoreState((state) => state.server.data?.node);
    const limits = ServerContext.useStoreState((state) => state.server.data?.limits);
    const inConflictState = ServerContext.useStoreState((state) => state.server.inConflictState);
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const allocation = ServerContext.useStoreState((state) => state.server.data?.allocations.find((a) => a.isDefault));
    const locationName = ServerContext.useStoreState((state) => state.server.data?.location);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);

    const to = (value: string, url = false) => {
        if (value === '/') {
            return url ? match.url : match.path;
        }
        return `${(url ? match.url : match.path).replace(/\/*$/, '')}/${value.replace(/^\/+/, '')}`;
    };

    useEffect(
        () => () => {
            clearServerState();
        },
        []
    );

    useEffect(() => {
        setError('');

        getServer(match.params.id).catch((error: any) => {
            console.error(error);
            setError(httpErrorToHuman(error));
        });

        return () => {
            clearServerState();
        };
    }, [match.params.id]);

    return (
        <React.Fragment key={'server-router'}>
            <NavigationBar />
            <div css={tw`flex min-h-screen pt-[3.5rem]`}>
                <Sidebar />
                <div css={tw`flex-1 ml-[240px] bg-neutral-800`}>
                    {!uuid || !id ? (
                        error ? (
                            <ServerError message={error} />
                        ) : (
                            <Spinner size={'large'} centered />
                        )
                    ) : (
                        <>
                            {/* Content Header with Server Info */}
                            <div tw="bg-neutral-700/50 border-b border-neutral-700 px-8 py-4 flex items-center justify-between">
                                <div tw="flex items-center">
                                    <div tw="mr-6 pr-6 border-r border-neutral-700">
                                        <h1 tw="text-xl font-header font-medium text-neutral-100 italic">{name}</h1>
                                        <p tw="text-xs text-neutral-400 mt-1">
                                            <span tw="font-mono bg-neutral-800 px-2 py-0.5 rounded mr-2 text-cyan-400 text-[10px]">
                                                {id}
                                            </span>
                                            {allocation ? `${allocation.ip}:${allocation.port}` : 'n/a'}
                                        </p>
                                    </div>
                                    <div tw="hidden lg:flex space-x-8">
                                        <div tw="flex flex-col">
                                            <span tw="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Location</span>
                                            <span tw="text-sm text-neutral-200">{locationName || 'n/a'}</span>
                                        </div>
                                        <div tw="flex flex-col">
                                            <span tw="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">CPU Limit</span>
                                            <span tw="text-sm text-neutral-200">{!limits || limits.cpu === 0 ? 'Unlimited' : `${limits.cpu}%`}</span>
                                        </div>
                                        <div tw="flex flex-col">
                                            <span tw="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Memory</span>
                                            <span tw="text-sm text-neutral-200">{!limits || limits.memory === 0 ? 'Unlimited' : bytesToString(mbToBytes(limits.memory))}</span>
                                        </div>
                                        <div tw="flex flex-col">
                                            <span tw="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Disk</span>
                                            <span tw="text-sm text-neutral-200">{!limits || limits.disk === 0 ? 'Unlimited' : bytesToString(mbToBytes(limits.disk))}</span>
                                        </div>
                                    </div>
                                </div>
                                <div tw="hidden md:flex flex-col items-end">
                                    <div tw="flex items-center bg-cyan-900/30 border border-cyan-800/50 rounded-full px-3 py-1">
                                        <div tw="w-2 h-2 rounded-full bg-cyan-400 mr-2 shadow-[0_0_8px_rgba(34,211,238,0.6)]" />
                                        <span tw="text-[10px] uppercase font-bold text-cyan-100 tracking-wider">Server Connected</span>
                                    </div>
                                </div>
                            </div>

                            <InstallListener />
                            <TransferListener />
                            <WebsocketHandler />
                            {inConflictState && (!rootAdmin || (rootAdmin && !location.pathname.endsWith(`/server/${id}`))) ? (
                                <ConflictStateRenderer />
                            ) : (
                                <ErrorBoundary>
                                    <TransitionRouter>
                                        <Switch location={location}>
                                            {routes.server.map(({ path, permission, component: Component }) => (
                                                <PermissionRoute key={path} permission={permission} path={to(path)} exact>
                                                    <Spinner.Suspense>
                                                        <Component />
                                                    </Spinner.Suspense>
                                                </PermissionRoute>
                                            ))}
                                            <Route path={'*'} component={NotFound} />
                                        </Switch>
                                    </TransitionRouter>
                                </ErrorBoundary>
                            )}
                        </>
                    )}
                </div>
            </div>
        </React.Fragment>
    );
};
