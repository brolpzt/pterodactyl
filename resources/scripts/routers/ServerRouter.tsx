import TransferListener from '@/components/server/TransferListener';
import React, { useEffect, useState } from 'react';
import { NavLink, Redirect, Route, Switch, useParams, useRouteMatch, matchPath } from 'react-router-dom';
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
import { useLocation } from 'react-router';
import ConflictStateRenderer from '@/components/server/ConflictStateRenderer';
import PermissionRoute from '@/components/elements/PermissionRoute';
import routes from '@/routers/routes';
import { isCs16Server } from '@/lib/isCs16Server';

import Sidebar from '@/components/Sidebar';
import { MainContent } from '@/components/layout/MainContent';
import tw from 'twin.macro';
import { breadcrumbCurrentText, navText } from '@/assets/css/cardTheme';
import useFlash from '@/plugins/useFlash';
import ContentContainer from '@/components/elements/ContentContainer';
import ServerBackground from '@/components/server/ServerBackground';
import ServerStatusBar from '@/components/server/ServerStatusBar';
import useScrollToTopOnRouteChange from '@/plugins/useScrollToTopOnRouteChange';
import { useTranslation } from 'react-i18next';

export default () => {
    const { t } = useTranslation('strings');
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
        return () => clearServerState();
    }, []);

    useEffect(() => {
        if (!routeServerId) {
            return;
        }

        clearServerState();
        setError('');
        getServer(routeServerId).catch((error: any) => {
            console.error(error);
            setError(httpErrorToHuman(error));
        });
    }, [routeServerId]);

    const sidebarCollapsed = useStoreState((state: any) => state.sidebarCollapsed);
    const isTs3 = eggId === 12;
    const isCs16 = isCs16Server(gamedig, eggName);
    const isBlockedRouteForTs3 = (path: string) =>
        path === '/backups' || ((!rootAdmin) && (path === '/console' || path === '/files' || path === '/files/:action(edit|new)'));
    const isTs3OnlyRoute = (path: string) => path.startsWith('/ts3');
    const isAmxxOnlyRoute = (path: string) => path.startsWith('/amxx');
    const isRootAdminOnlyTs3Route = (path: string) => path === '/ts3/query';

    return (
        <React.Fragment key={'server-router'}>
            <Sidebar />
            <NavigationBar />
            {uuid && id && (
                <ServerBackground key={uuid} viewport gamedig={gamedig} eggName={eggName} />
            )}
            <div css={tw`relative z-10 flex min-h-screen pt-[3.5rem]`}>
                <MainContent $collapsed={sidebarCollapsed}>
                    {!uuid || !id ? (
                        <div css={tw`min-h-full bg-neutral-900`}>
                            {error ? (
                                <ServerError message={error} />
                            ) : (
                                <Spinner size={'large'} centered />
                            )}
                        </div>
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
                            <div tw="mt-0">
                                <ContentContainer>
                                    <nav
                                        aria-label="Breadcrumb"
                                        css={[navText, tw`my-4 inline-flex items-center text-sm flex-wrap`]}
                                    >
                                            <NavLink to="/" css={[navText, tw`no-underline hover:text-white transition-colors duration-150`]}>{t('nav.dashboard')}</NavLink>
                                            <span css={[navText, tw`mx-2 opacity-50`]}>/</span>
                                            <NavLink to={`/server/${routeServerId}`} css={[navText, tw`no-underline hover:text-white transition-colors duration-150`]}>{name}</NavLink>
                                            {(() => {
                                                const current = routes.server.find((r) =>
                                                    matchPath(location.pathname, { path: to(r.path, true), exact: r.exact })
                                                );
                                                if (!current?.name || current.path === '/') return null;

                                                const label = current.nameKey ? t(current.nameKey) : current.name;

                                                return (
                                                    <>
                                                        <span css={[navText, tw`mx-2 opacity-50`]}>/</span>
                                                        <span css={breadcrumbCurrentText}>{label}</span>
                                                    </>
                                                );
                                            })()}
                                        </nav>
                                </ContentContainer>

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
                                                    (isTs3 && isBlockedRouteForTs3(path)) ||
                                                    (!isTs3 && isTs3OnlyRoute(path)) ||
                                                    (!isCs16 && isAmxxOnlyRoute(path)) ||
                                                    (isTs3 && isRootAdminOnlyTs3Route(path) && !rootAdmin) ? (
                                                        <Route key={path} path={to(path)} exact>
                                                            <Redirect to={to('/')} />
                                                        </Route>
                                                    ) : (
                                                        <PermissionRoute key={path} permission={permission} path={to(path)} exact>
                                                            <Spinner.Suspense>
                                                                <Component />
                                                            </Spinner.Suspense>
                                                        </PermissionRoute>
                                                    )
                                                ))}
                                                <Route path={'*'} component={NotFound} />
                                            </Switch>
                                        </TransitionRouter>
                                    </ErrorBoundary>
                                )}
                            </div>
                        </div>
                    )}
                </MainContent>
            </div>
        </React.Fragment >
    );
};
