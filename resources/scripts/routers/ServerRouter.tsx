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
    const inConflictState = ServerContext.useStoreState((state) => state.server.inConflictState);
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const allocation = ServerContext.useStoreState((state) => state.server.data?.allocations.find((a) => a.isDefault));
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
                                <div>
                                    <h1 tw="text-xl font-header font-medium text-neutral-100 italic">{name}</h1>
                                    <p tw="text-xs text-neutral-400 mt-1">
                                        <span tw="font-mono bg-neutral-800 px-2 py-0.5 rounded mr-2 text-cyan-400">
                                            {id}
                                        </span>
                                        {allocation ? `${allocation.ip}:${allocation.port}` : 'n/a'}
                                    </p>
                                </div>
                                <div tw="hidden md:flex flex-col items-end">
                                    <span tw="text-[10px] uppercase font-bold text-neutral-500 tracking-wider">Node</span>
                                    <span tw="text-sm text-neutral-300">{node}</span>
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
