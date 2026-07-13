import * as React from 'react';
import { Link, NavLink, useRouteMatch } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCogs,
    faLayerGroup,
    faTerminal,
    faFolderOpen,
    faDatabase,
    faCalendarAlt,
    faUsers,
    faCloudUploadAlt,
    faNetworkWired,
    faListUl,
    faPuzzlePiece,
    faShieldAlt,
    faGlobe,
    faExternalLinkAlt,
    faUserPlus,
    faPlug,
    faMicrochip,
    faAngleDoubleLeft,
    faAngleDoubleRight,
    faArrowLeft,
    faGamepad,
    faStore,
} from '@fortawesome/free-solid-svg-icons';
import { useStoreState, useStoreActions } from '@/state/hooks';
import { ApplicationStore } from '@/state';
import { ServerContext } from '@/state/server';
import tw from 'twin.macro';
import styled, { css } from 'styled-components/macro';
import Can from '@/components/elements/Can';
import routes from '@/routers/routes';
import { useTranslation } from 'react-i18next';
import { motionDurations } from '@/assets/css/motionTheme';
import { glassContentLayer, glassSidebarShell } from '@/assets/css/glassPanel';
import ScrollArea from '@/components/elements/ScrollArea';
import { isCs16Server } from '@/lib/isCs16Server';
import { HOSTGAMER_LOGO_SRC } from '@/lib/branding';
import { HEADER_HEIGHT, sidebarWidthRule } from '@/lib/sidebarLayout';

const navItemText = css`
    color: var(--hg-nav-text);
`;

const CLIENT_ACCOUNT_URL = 'https://hostgamer.net/account';
const CLIENT_SERVICES_URL = `${CLIENT_ACCOUNT_URL}/services`;

const serverLinksFade = `
    @keyframes fadeInItems {
        from { opacity: 0; transform: translateY(4px); }
        to   { opacity: 1; transform: translateY(0); }
    }
`;

const FadeInWrapper = styled.div`
    animation: fadeInItems ${motionDurations.orderFade}ms ease-out both;
`;

const ServerLinksPlaceholder = () => {
    const collapsed = useStoreState((state) => state.sidebarCollapsed);
    return (
        <>
            {Array.from({ length: 5 }).map((_, i) => (
                <div
                    key={i}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: collapsed ? 'center' : 'flex-start',
                        padding: '0.625rem 1.25rem',
                        opacity: 0.3,
                    }}
                >
                    <div style={{ width: '1.25rem', height: '1rem', borderRadius: '4px', background: '#4b5563', flexShrink: 0 }} />
                    {!collapsed && <div style={{ width: '80px', height: '0.8rem', borderRadius: '4px', background: '#4b5563', marginLeft: '0.5rem' }} />}
                </div>
            ))}
        </>
    );
};

const SidebarContainer = styled.div.attrs({ className: 'hg-glass-sidebar hg-layout-sidebar' })<{ collapsed: boolean }>`
    ${glassSidebarShell};
    ${tw`flex flex-col fixed left-0 top-0 bottom-0 z-40`};
    transition: width ${motionDurations.layout}ms ease;
    ${(props) => sidebarWidthRule(props.collapsed)};

    @media (max-width: 767px) {
        z-index: 45;

        ${(props) =>
            props.collapsed &&
            css`
                overflow: hidden;
                pointer-events: none;
                box-shadow: none;

                &::before {
                    opacity: 0;
                }
            `}
    }
`;

const SidebarInner = styled.div`
    ${glassContentLayer};
`;

const SidebarBrand = styled(Link)<{ collapsed: boolean }>`
    ${tw`flex items-center justify-center flex-shrink-0 no-underline transition-all duration-200 overflow-hidden`};
    height: ${HEADER_HEIGHT};
    padding: ${(props) => (props.collapsed ? '0 0.75rem' : '0 1.25rem')};

    &:hover img {
        ${tw`opacity-90`};
    }
`;

const BrandLogo = styled.img<{ collapsed: boolean }>`
    ${tw`block transition-all duration-200`};
    height: ${(props) => (props.collapsed ? '1.25rem' : '1.375rem')};
    width: ${(props) => (props.collapsed ? '2rem' : 'auto')};
    max-width: ${(props) => (props.collapsed ? '2rem' : '11.5rem')};
    object-fit: ${(props) => (props.collapsed ? 'cover' : 'contain')};
    object-position: left center;
`;

const NavItemLabel = styled.span.attrs({ className: 'nav-item-label' })<{ collapsed: boolean }>`
    ${navItemText};
    ${tw`transition-all duration-150 whitespace-nowrap overflow-hidden`};
    ${props => props.collapsed ? 'max-width: 0; opacity: 0; margin: 0;' : 'max-width: 200px; opacity: 1; margin-left: 0.5rem;'};
`;

const NavSectionTitle = styled.div<{ collapsed: boolean }>`
    ${tw`px-4 pt-4 pb-3 font-header text-[10px] font-bold text-neutral-400 uppercase tracking-widest truncate transition-colors duration-200`};
    ${(props) => props.collapsed && tw`text-transparent`};
`;

const SidebarFooter = styled.div<{ collapsed: boolean }>`
    ${tw`p-4 border-t text-[10px] text-neutral-400 font-medium transition-all duration-200`};
    border-color: var(--hg-border);
    ${(props) => props.collapsed && tw`text-center px-0`};
`;

const NavItem = styled(NavLink)<{ collapsed?: boolean }>`
    ${navItemText};
    ${tw`relative z-10 flex items-center py-2.5 text-sm font-header font-semibold uppercase no-underline transition-all duration-150 hover:bg-white/5`};
    padding-left: 1.25rem;
    padding-right: 1.25rem;
    justify-content: ${(props) => (props.collapsed ? 'center' : 'flex-start')};

    &:hover .nav-item-label {
        ${tw`text-white`};
    }

    &:hover .icon-container {
        ${navItemText};
    }

    &.active {
        ${tw`bg-primary-500 text-white border-r-2 border-primary-500`};

        & .nav-item-label,
        & .icon-container {
            ${tw`text-white`};
        }
    }
`;

const ExternalNavItem = styled.a<{ collapsed?: boolean }>`
    ${navItemText};
    ${tw`relative z-10 flex items-center py-2.5 text-sm font-header font-semibold uppercase no-underline transition-all duration-150 hover:bg-white/5`};
    padding-left: 1.25rem;
    padding-right: 1.25rem;
    justify-content: ${props => props.collapsed ? 'center' : 'flex-start'};

    &:hover .nav-item-label {
        ${tw`text-white`};
    }

    &:hover .icon-container {
        ${navItemText};
    }
`;

const IconContainer = styled.div<{ collapsed?: boolean }>`
    ${navItemText};
    ${tw`flex items-center justify-center flex-shrink-0 transition-colors duration-150`};
    width: 1.25rem;
`;

const SectionTitle = ({ children, collapsed }: { children: React.ReactNode, collapsed: boolean }) => (
    <NavSectionTitle collapsed={collapsed}>{children}</NavSectionTitle>
);

const Divider = styled.div`
    ${tw`mx-4 my-2 border-t`};
    border-color: var(--hg-border);
`;

const SidebarScroll = ({ children, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
    <ScrollArea variant={'thin'} css={tw`flex-1 pb-4`} {...props}>
        {children}
    </ScrollArea>
);

const ServerLinks = () => {
    const internalId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const eggId = ServerContext.useStoreState((state) => state.server.data?.eggId);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data?.eggFeatures ?? []);
    const dnsEnabled = ServerContext.useStoreState((state) => state.server.data?.dnsEnabled ?? false);
    const gamedig = ServerContext.useStoreState((state) => state.server.data?.gamedig);
    const eggName = ServerContext.useStoreState((state) => state.server.data?.egg);
    const rootAdmin = useStoreState((state: any) => state.user.data!.rootAdmin);
    const { t } = useTranslation('strings');
    const isTs3 = eggId === 12;
    const isCs16 = isCs16Server(gamedig, eggName);
    const hasWebRcon = eggFeatures.includes('webrcon');

    const match = useRouteMatch<{ id: string }>('/server/:id');

    const to = (value: string) => {
        if (!match) return '/';
        const url = `/server/${match.params.id}`;
        if (value === '/') return url;
        return `${url.replace(/\/*$/, '')}/${value.replace(/^\/+/, '')}`;
    };

    const getIcon = (nameKey: string | undefined, name: string | undefined) => {
        const key = nameKey || name || '';
        switch (key) {
            case 'server.home':
            case 'Início': return faLayerGroup;
            case 'server.console':
            case 'Console': return faTerminal;
            case 'server.files':
            case 'Files': return faFolderOpen;
            case 'server.databases':
            case 'Databases': return faDatabase;
            case 'server.schedules':
            case 'Schedules': return faCalendarAlt;
            case 'server.users':
            case 'Users': return faUsers;
            case 'server.backups':
            case 'Backups': return faCloudUploadAlt;
            case 'server.network':
            case 'Network': return faNetworkWired;
            case 'server.settings':
            case 'Settings': return faCogs;
            case 'server.activity':
            case 'Activity': return faListUl;
            case 'server.addons':
            case 'Addons': return faPuzzlePiece;
            case 'server.firewall':
            case 'Firewall': return faShieldAlt;
            case 'server.dns':
            case 'DNS': return faGlobe;
            case 'server.workshop':
            case 'Workshop': return faStore;
            case 'server.amxx.web':
            case 'AMXX Web': return faGamepad;
            case 'server.amxx.admins':
            case 'AMXX Admins': return faUsers;
            case 'server.amxx.bans':
            case 'AMXX Bans': return faShieldAlt;
            case 'server.webrcon.web':
            case 'WebRCON': return faTerminal;
            case 'server.ts3.snapshots':
            case 'Snapshots': return faCloudUploadAlt;
            case 'server.ts3.bans':
            case 'Bans': return faShieldAlt;
            case 'server.ts3.tokens':
            case 'Tokens': return faPlug;
            case 'server.ts3.logs':
            case 'Logs': return faListUl;
            case 'server.ts3.viewer':
            case 'TS3 Viewer': return faExternalLinkAlt;
            case 'server.ts3.query':
            case 'Query Terminal': return faTerminal;
            default: return faLayerGroup;
        }
    };

    const collapsed = useStoreState((state) => state.sidebarCollapsed);
    const ts3RouteOrder = [
        '/',
        '/console',
        '/files',
        '/dns',
        '/ts3/snapshots',
        '/ts3/bans',
        '/ts3/tokens',
        '/ts3/logs',
        '/ts3/query',
        '/activity',
        '/ts3/html-viewer',
        '/settings',
    ];
    const cs16RouteOrder = [
        '/',
        '/console',
        '/files',
        '/addons',
        '/firewall',
        '/dns',
        '/schedules',
        '/backups',
        '/users',
        '/network',
        '/activity',
        '/settings',
    ];
    const amxxRouteOrder = ['/amxx', '/amxx/admins', '/amxx/bans'];
    const webrconRouteOrder = ['/webrcon'];

    const isAmxxRoute = (path: string) => path.startsWith('/amxx');
    const isWebRconRoute = (path: string) => path.startsWith('/webrcon');

    const sortRoutes = (routeList: typeof routes.server, order: string[]) =>
        routeList
            .filter((route) => !!route.name)
            .sort((a, b) => {
                const aIndex = order.indexOf(a.path);
                const bIndex = order.indexOf(b.path);
                const safeA = aIndex === -1 ? Number.MAX_SAFE_INTEGER : aIndex;
                const safeB = bIndex === -1 ? Number.MAX_SAFE_INTEGER : bIndex;

                return safeA - safeB;
            });

    const renderServerRoute = (route: (typeof routes.server)[number]) => (
        route.permission ? (
            <Can key={route.path} action={route.permission as any} matchAny>
                <NavItem
                    to={to(route.path)}
                    exact={route.exact}
                    collapsed={collapsed}
                    title={route.nameKey ? t(route.nameKey) : route.name!}
                >
                    <IconContainer className="icon-container">
                        <FontAwesomeIcon icon={getIcon((route as any).nameKey, route.name!)} />
                    </IconContainer>
                    <NavItemLabel collapsed={collapsed}>
                        {route.nameKey ? t(route.nameKey) : route.name!}
                    </NavItemLabel>
                </NavItem>
            </Can>
        ) : (
            <NavItem
                key={route.path}
                to={to(route.path)}
                exact={route.exact}
                collapsed={collapsed}
                title={route.nameKey ? t(route.nameKey) : route.name!}
            >
                <IconContainer className="icon-container">
                    <FontAwesomeIcon icon={getIcon((route as any).nameKey, route.name!)} />
                </IconContainer>
                <NavItemLabel collapsed={collapsed}>
                    {route.nameKey ? t(route.nameKey) : route.name!}
                </NavItemLabel>
            </NavItem>
        )
    );

    const filteredRoutes = routes.server
        .filter((route) => (isTs3
            ? route.path.startsWith('/ts3') || route.path === '/' || route.path === '/activity' || route.path === '/settings' || route.path === '/console' || route.path === '/files' || route.path === '/dns'
            : isCs16
            ? !route.path.startsWith('/ts3')
            : !route.path.startsWith('/ts3') && !route.path.startsWith('/amxx')))
        .filter((route) => !(isTs3 && route.path === '/backups'))
        .filter((route) => !(isTs3 && (route.path === '/console' || route.path === '/files') && !rootAdmin))
        .filter((route) => route.path !== '/ts3/query' || rootAdmin)
        .filter((route) => !(isCs16 && isAmxxRoute(route.path)))
        .filter((route) => !(hasWebRcon && isWebRconRoute(route.path)))
        .filter((route) => route.path !== '/dns' || dnsEnabled || eggFeatures.includes('dns'))
        .filter((route) => route.path !== '/workshop' || eggFeatures.includes('workshop'));

    const sortedRoutes = isTs3
        ? sortRoutes(filteredRoutes, ts3RouteOrder)
        : isCs16
        ? sortRoutes(filteredRoutes, cs16RouteOrder)
        : filteredRoutes.filter((route) => !!route.name);

    const amxxRoutes = isCs16
        ? sortRoutes(
            routes.server.filter((route) => isAmxxRoute(route.path)),
            amxxRouteOrder
        )
        : [];

    const webrconRoutes = hasWebRcon
        ? sortRoutes(
            routes.server.filter((route) => isWebRconRoute(route.path)),
            webrconRouteOrder
        )
        : [];

    return (
        <>
            {(amxxRoutes.length > 0 || webrconRoutes.length > 0) && (
                <>
                    <SectionTitle collapsed={collapsed}>{t('nav.quick_menu')}</SectionTitle>
                    {amxxRoutes.map((route) => renderServerRoute(route))}
                    {webrconRoutes.map((route) => renderServerRoute(route))}
                </>
            )}

            <SectionTitle collapsed={collapsed}>{t('nav.server_menu')}</SectionTitle>
            {sortedRoutes.map((route) => renderServerRoute(route))}

            {rootAdmin && internalId && (
                <ExternalNavItem
                    href={`/admin/servers/view/${internalId}`}
                    target={'_blank'}
                    rel="noreferrer"
                    collapsed={collapsed}
                    title={t('nav.admin_view')}
                >
                    <IconContainer className="icon-container">
                        <FontAwesomeIcon icon={faExternalLinkAlt} />
                    </IconContainer>
                    <NavItemLabel collapsed={collapsed}>{t('nav.admin_view')}</NavItemLabel>
                </ExternalNavItem>
            )}

            <Divider />

            <ExternalNavItem
                href={CLIENT_SERVICES_URL}
                collapsed={collapsed}
                title={collapsed ? t('nav.back_to_client_area') : undefined}
            >
                <IconContainer className="icon-container">
                    <FontAwesomeIcon icon={faArrowLeft} />
                </IconContainer>
                <NavItemLabel collapsed={collapsed}>{t('nav.back_to_client_area')}</NavItemLabel>
            </ExternalNavItem>
        </>
    );
};

const Sidebar = () => {
    const match = useRouteMatch<{ id: string }>('/server/:id');
    const collapsed = useStoreState((state) => state.sidebarCollapsed);
    const toggleSidebar = useStoreActions((actions) => actions.toggleSidebar);
    const { t } = useTranslation('strings');

    return (
        <SidebarContainer collapsed={collapsed}>
            <SidebarInner>
                <SidebarBrand to={'/'} collapsed={collapsed} title={t('nav.dashboard')}>
                    <BrandLogo
                        collapsed={collapsed}
                        src={HOSTGAMER_LOGO_SRC}
                        alt={'HostGamer'}
                        draggable={false}
                    />
                </SidebarBrand>
                <SidebarScroll>
                {match && (
                    <React.Suspense fallback={<ServerLinksPlaceholder />}>
                        <FadeInWrapper key={match.params.id}>
                            <ServerLinks />
                        </FadeInWrapper>
                    </React.Suspense>
                )}
            </SidebarScroll>

                <SidebarFooter collapsed={collapsed}>
                    {collapsed ? 'v2.0' : 'HostGamer Control v2.0'}
                </SidebarFooter>
            </SidebarInner>
        </SidebarContainer>
    );
};

export default Sidebar;
