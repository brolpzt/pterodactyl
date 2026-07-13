import TransferListener from '@/components/server/TransferListener';
import React, { Suspense, useEffect, useState } from 'react';
import { Redirect, Route, Switch, useParams, useRouteMatch } from 'react-router-dom';
import NavigationBar from '@/components/NavigationBar';
import TransitionRouter from '@/TransitionRouter';
import WebsocketHandler from '@/components/server/WebsocketHandler';
import { ServerContext } from '@/state/server';
import { CSSTransition } from 'react-transition-group';
import Spinner from '@/components/elements/Spinner';
import { NotFound, ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import { useStoreState, useStoreActions } from '@/state/hooks';
import SubNavigation from '@/components/elements/SubNavigation';
import InstallListener from '@/components/server/InstallListener';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { useLocation } from 'react-router';
import ConflictStateRenderer from '@/components/server/ConflictStateRenderer';
import PermissionRoute from '@/components/elements/PermissionRoute';
import routes from '@/routers/routes';
import { isCs16Server } from '@/lib/isCs16Server';

import Sidebar from '@/components/Sidebar';
import { MainContent } from '@/components/layout/MainContent';
import PageFallbackBackground from '@/components/layout/PageFallbackBackground';
import tw from 'twin.macro';
import useFlash from '@/plugins/useFlash';
import ServerBackground from '@/components/server/ServerBackground';
import { getServerBackgroundUrl } from '@/lib/serverBackgrounds';
import ServerStatusBar from '@/components/server/ServerStatusBar';
import SlotMismatchAlert from '@/components/server/SlotMismatchAlert';
import HostnameBrandingAlert from '@/components/server/HostnameBrandingAlert';
import useScrollToTopOnRouteChange from '@/plugins/useScrollToTopOnRouteChange';

export default () => {
    const { id: routeServerId } = useParams<{ id: string }>();
    const match = useRouteMatch<{ id: string }>('/server/:id');
    const location = useLocation();
    useScrollToTopOnRouteChange();

    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [error, setError] = useState('');

    const name = ServerContext.useStoreState((state) => state.server.data?.name);
    const id = ServerContext.useStoreState((state) => state.server.data?.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const node = ServerContext.useStoreState((state) => state.server.data?.node);
    const limits = ServerContext.useStoreState((state) => state.server.data?.limits);
    const inConflictState = ServerContext.useStoreState((state) => state.server.inConflictState);
    const internalId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const eggId = ServerContext.useStoreState((state) => state.server.data?.eggId);
    const gamedig = ServerContext.useStoreState((state) => state.server.data?.gamedig);
    const eggName = ServerContext.useStoreState((state) => state.server.data?.egg);
    const allocation = ServerContext.useStoreState((state) => state.server.data?.allocations.find((a) => a.isDefault));
    const locationName = ServerContext.useStoreState((state) => state.server.data?.location);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);
    const { addFlash, clearFlashes } = useFlash();

    const onCopyText = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        clearFlashes('server:copy');
        addFlash({ key: 'server:copy', type: 'success', message: `${label} copiado para a área de transferência!` });
    };

    const to = (value: string, url = false) => {
        if (!match) {
            return value === '/' ? `/server/${routeServerId}` : `/server/${routeServerId}/${value.replace(/^\/+/, '')}`;
        }

        if (value === '/') {
            return url ? match.url : match.path;
        }
        return `${(url ? match.url : match.path).replace(/\/*$/, '')}/${value.replace(/^\/+/, '')}`;
    };

    useEffect(() => {
        if (!routeServerId) {
            return;
        }

        if (id === routeServerId) {
            return;
        }

        clearServerState();
        setError('');
        getServer(routeServerId).catch((error: any) => {
            console.error(error);
            setError(httpErrorToHuman(error));
        });
        // `id` is read intentionally without being a dependency — including it would
        // re-trigger a fetch right after clearServerState() resets the store.
    }, [routeServerId, clearServerState, getServer]);

    useEffect(() => {
        const url = getServerBackgroundUrl(gamedig, eggName);
        if (!url) {
            return;
        }

        const image = new Image();
        image.decoding = 'async';
        image.src = url;
    }, [gamedig, eggName]);

    const sidebarCollapsed = useStoreState((state) => state.sidebarCollapsed);
    const setSidebarCollapsed = useStoreActions((actions) => actions.setSidebarCollapsed);

    useEffect(() => {
        setSidebarCollapsed(false);
    }, [routeServerId, setSidebarCollapsed]);

    const isTs3 = eggId === 12;
    const isCs16 = isCs16Server(gamedig, eggName);
    const isBlockedRouteForTs3 = (path: string) =>
        path === '/backups' || ((!rootAdmin) && (path === '/console' || path === '/files' || path === '/files/:action(edit|new)'));
    const isTs3OnlyRoute = (path: string) => path.startsWith('/ts3');
    const isAmxxOnlyRoute = (path: string) => path.startsWith('/amxx');
    const isRootAdminOnlyTs3Route = (path: string) => path === '/ts3/query';

    const serverReady = Boolean(uuid && id);
    const routeContentFallback = (
        <div css={tw`flex items-center justify-center min-h-[50vh]`}>
            <Spinner size={'large'} />
        </div>
    );

    return (
        <React.Fragment key={'server-router'}>
            <Sidebar />
            <NavigationBar />
            <PageFallbackBackground />
            {uuid && id && (
                <ServerBackground key={uuid} viewport gamedig={gamedig} eggName={eggName} />
            )}
            <div css={tw`relative z-10 flex min-h-screen pt-[3.5rem]`}>
                <MainContent $collapsed={sidebarCollapsed}>
                    {error && !serverReady ? (
                        <ServerError message={error} />
                    ) : !serverReady ? (
                        routeContentFallback
                    ) : (
                        <div css={tw`relative min-h-full`}>
                            <ServerStatusBar
                                name={name}
                                id={id}
                                allocation={allocation}
                                locationName={locationName}
                                onCopyText={onCopyText}
                                formatIp={ip}
                            />
                            <SlotMismatchAlert />
                            <HostnameBrandingAlert />
                            <div tw="mt-0">
                                <InstallListener />
                                <TransferListener />
                                <WebsocketHandler />
                                {inConflictState && (!rootAdmin || (rootAdmin && !location.pathname.endsWith(`/server/${id}`))) ? (
                                    <ConflictStateRenderer />
                                ) : (
                                    <ErrorBoundary>
                                        <TransitionRouter>
                                            <Suspense fallback={routeContentFallback}>
                                                <Switch location={location}>
                                                    {routes.server.map(({ path, permission, component: Component }) => (
                                                        (isTs3 && isBlockedRouteForTs3(path)) ||
                                                        (!isTs3 && isTs3OnlyRoute(path)) ||
                                                        (!isCs16 && isAmxxOnlyRoute(path)) ||
                                                        (isTs3 && isRootAdminOnlyTs3Route(path) && !rootAdmin) ? (
                                                            <Route key={path} path={to(path)} exact>
                                                                <Redirect to={to('/')} />
                                                            </Route>
                                                        ) : (
                                                            <PermissionRoute key={path} permission={permission} path={to(path)} exact>
                                                                <Component />
                                                            </PermissionRoute>
                                                        )
                                                    ))}
                                                    <Route path={'*'} component={NotFound} />
                                                </Switch>
                                            </Suspense>
                                        </TransitionRouter>
                                    </ErrorBoundary>
                                )}
                            </div>
                        </div>
                    )}
                </MainContent>
            </div>
        </React.Fragment>
    );
};
