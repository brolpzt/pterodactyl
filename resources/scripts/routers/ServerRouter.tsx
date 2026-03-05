import TransferListener from '@/components/server/TransferListener';
import React, { useEffect, useState } from 'react';
import { NavLink, Route, Switch, useRouteMatch, matchPath } from 'react-router-dom';
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
import { faExternalLinkAlt, faCopy } from '@fortawesome/free-solid-svg-icons';
import { useLocation } from 'react-router';
import ConflictStateRenderer from '@/components/server/ConflictStateRenderer';
import PermissionRoute from '@/components/elements/PermissionRoute';
import routes from '@/routers/routes';

import Sidebar from '@/components/Sidebar';
import tw from 'twin.macro';
import useFlash from '@/plugins/useFlash';
import PowerButtons from '@/components/server/console/PowerButtons';
import ContentContainer from '@/components/elements/ContentContainer';
import Fade from '@/components/elements/Fade';

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
    const eggName = ServerContext.useStoreState((state) => state.server.data?.egg);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);
    const variables = ServerContext.useStoreState((state) => state.server.data?.variables);

    const password = variables?.find((v) => v.envVariable === 'SV_PASSWORD')?.serverValue || '';
    const connectionString = allocation ? `connect ${allocation.ip}:${allocation.port};${password ? ` password ${password};` : ''}` : '';

    const { addFlash, clearFlashes } = useFlash();

    const onCopyText = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        clearFlashes('server:copy');
        addFlash({ key: 'server:copy', type: 'success', message: `${label} copiado para a área de transferência!` });
    };

    const getFlagEmoji = (code: string) => {
        if (!code || code.length < 2) return '🌐';
        const countryCode = code.toUpperCase().substring(0, 2);
        return countryCode
            .split('')
            .map((char) => String.fromCodePoint(char.charCodeAt(0) + 127397))
            .join('');
    };

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

    const sidebarCollapsed = useStoreState((state: any) => state.sidebarCollapsed);

    return (
        <React.Fragment key={'server-router'}>
            <NavigationBar />
            <div css={tw`flex min-h-screen pt-[3.5rem]`}>
                <Sidebar />
                <div
                    css={tw`flex-1 bg-neutral-800 transition-all duration-300`}
                    style={{ marginLeft: sidebarCollapsed ? '70px' : '240px' }}
                >
                    {!uuid || !id ? (
                        error ? (
                            <ServerError message={error} />
                        ) : (
                            <Spinner size={'large'} centered />
                        )
                    ) : (
                        <>
                            {/* Content Header with Server Info — fades in with the same animation as page content */}
                            <div tw="sticky top-[3.5rem] z-30">
                                <Fade timeout={150} in appear>
                                    <div tw="bg-neutral-700/95 border-b border-neutral-700 px-4 sm:px-6 py-3 flex flex-nowrap items-center shadow-md backdrop-blur-sm overflow-hidden">
                                        {/* Server name + ID/IP — always visible, flex-shrink-0 so it never collapses */}
                                        <div tw="flex items-center flex-shrink-0 mr-4 sm:mr-6 pr-4 sm:pr-6 border-r border-neutral-700 min-w-0">
                                            <div tw="min-w-0">
                                                <h1 tw="text-lg sm:text-xl font-header font-medium text-neutral-100 whitespace-nowrap truncate max-w-[140px] sm:max-w-[220px]">{name}</h1>
                                                <p tw="text-[13px] text-neutral-400 mt-0.5 flex items-center whitespace-nowrap">
                                                    <span tw="font-mono bg-neutral-800 px-1.5 py-0.5 rounded mr-2 text-neutral-100 text-[12px] flex-shrink-0">
                                                        {id}
                                                    </span>
                                                    <span
                                                        tw="cursor-pointer hover:text-neutral-200 transition-colors duration-150 flex items-center flex-shrink-0 text-[13px]"
                                                        onClick={() => allocation && onCopyText(`${allocation.ip}:${allocation.port}`, 'IP')}
                                                        title="Clique para copiar"
                                                    >
                                                        {allocation ? `${allocation.ip}:${allocation.port}` : 'n/a'}
                                                        <FontAwesomeIcon icon={faCopy} tw="ml-1.5 text-[10px] text-neutral-500" />
                                                    </span>
                                                </p>
                                            </div>
                                        </div>

                                        {/* Info columns — progressive disclosure, never wrap */}
                                        <div tw="hidden md:flex items-center gap-6 lg:gap-8 flex-shrink-0 overflow-hidden">
                                            {/* Localização — md+ */}
                                            <div tw="flex flex-col flex-shrink-0">
                                                <span tw="text-[10px] uppercase font-bold text-neutral-500 tracking-wider whitespace-nowrap">Localização</span>
                                                <span tw="text-[13px] text-neutral-200 flex items-center whitespace-nowrap">
                                                    <span tw="mr-1 text-sm">{locationName ? getFlagEmoji(locationName.split(' ')[0]) : '🌐'}</span>
                                                    <span tw="max-w-[80px] truncate">{locationName || 'n/a'}</span>
                                                </span>
                                            </div>
                                            {/* Players — lg+ */}
                                            <div tw="hidden lg:flex flex-col flex-shrink-0">
                                                <span tw="text-[10px] uppercase font-bold text-neutral-500 tracking-wider whitespace-nowrap">Players</span>
                                                <span tw="text-[13px] text-neutral-200 whitespace-nowrap">12 / 32</span>
                                            </div>
                                            {/* Mapa — lg+ */}
                                            <div tw="hidden lg:flex flex-col flex-shrink-0">
                                                <span tw="text-[10px] uppercase font-bold text-neutral-500 tracking-wider whitespace-nowrap">Mapa</span>
                                                <span tw="text-[13px] text-neutral-200 whitespace-nowrap">cs_assault_up</span>
                                            </div>
                                            {/* Egg — xl+ */}
                                            <div tw="hidden xl:flex flex-col flex-shrink-0">
                                                <span tw="text-[10px] uppercase font-bold text-neutral-500 tracking-wider whitespace-nowrap">Egg</span>
                                                <span tw="text-[13px] text-neutral-200 whitespace-nowrap">{eggName || 'n/a'}</span>
                                            </div>
                                            {/* String de conexão — xl+ */}
                                            <div tw="hidden xl:flex flex-col flex-shrink-0">
                                                <span tw="text-[10px] uppercase font-bold text-neutral-500 tracking-wider whitespace-nowrap">Conexão</span>
                                                <span
                                                    tw="text-[13px] text-neutral-200 cursor-pointer hover:text-neutral-100 transition-colors duration-150 flex items-center whitespace-nowrap"
                                                    onClick={() => connectionString && onCopyText(connectionString, 'String de conexão')}
                                                    title="Clique para copiar"
                                                >
                                                    <span tw="max-w-[130px] truncate">{connectionString || 'n/a'}</span>
                                                    {connectionString && <FontAwesomeIcon icon={faCopy} tw="ml-1.5 text-[10px] text-neutral-500 flex-shrink-0" />}
                                                </span>
                                            </div>
                                        </div>

                                        {/* PowerButtons — always visible, pushed to the right */}
                                        <div tw="flex items-center ml-auto flex-shrink-0 pl-4">
                                            <PowerButtons tw="flex space-x-2" />
                                        </div>
                                    </div>
                                </Fade>
                            </div>
                            <div tw="mt-4 sm:mt-8">
                                <ContentContainer>
                                    <div tw="flex items-center text-[11px] uppercase tracking-wider text-neutral-500 mb-6 bg-neutral-700/30 px-3 py-1.5 rounded-md border border-neutral-700/50 flex-wrap">
                                        <NavLink to="/" tw="hover:text-neutral-300 transition-colors duration-150">Início</NavLink>
                                        <span tw="mx-2 text-neutral-600">/</span>
                                        <NavLink to={`/server/${match.params.id}`} tw="hover:text-neutral-300 transition-colors duration-150">{name}</NavLink>
                                        {routes.server.find(r => matchPath(location.pathname, { path: to(r.path, true), exact: r.exact }))?.name && (
                                            <>
                                                <span tw="mx-2 text-neutral-600">/</span>
                                                <span tw="text-neutral-200 font-bold">{routes.server.find(r => matchPath(location.pathname, { path: to(r.path, true), exact: r.exact }))?.name}</span>
                                            </>
                                        )}
                                    </div>
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
                            </div>
                        </>
                    )}
                </div>
            </div>
        </React.Fragment >
    );
};
