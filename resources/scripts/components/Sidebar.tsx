import * as React from 'react';
import { NavLink, useRouteMatch } from 'react-router-dom';
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
    faPlayCircle,
    faListUl,
    faPuzzlePiece,
    faShieldAlt,
    faExternalLinkAlt,
    faUserPlus,
    faPlug,
    faMicrochip,
    faAngleDoubleLeft,
    faAngleDoubleRight,
    faLifeRing,
    faReceipt,
    faArrowLeft,
} from '@fortawesome/free-solid-svg-icons';
import { useStoreState, useStoreActions } from '@/state/hooks';
import { ApplicationStore } from '@/state';
import { ServerContext } from '@/state/server';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import Can from '@/components/elements/Can';
import routes from '@/routers/routes';
import { useTranslation } from 'react-i18next';

const CLIENT_AREA_URL = 'https://clientarea.hostgamer.net';
const CLIENT_SERVICES_URL = 'https://hostgamer.net/account/services';

const serverLinksFade = `
    @keyframes fadeInItems {
        from { opacity: 0; transform: translateY(4px); }
        to   { opacity: 1; transform: translateY(0); }
    }
`;

const FadeInWrapper = styled.div`
    animation: fadeInItems 200ms ease-out both;
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

const SidebarContainer = styled.div<{ collapsed: boolean }>`
    ${tw`flex flex-col bg-neutral-900 shadow-md border-r border-neutral-800 fixed left-0 bottom-0 z-40 transition-all duration-300`};
    width: ${props => props.collapsed ? '70px' : '240px'};
    top: 3.5rem;
`;

const NavItemLabel = styled.span<{ collapsed: boolean }>`
    ${tw`transition-all duration-300 whitespace-nowrap overflow-hidden`};
    ${props => props.collapsed ? 'max-width: 0; opacity: 0; margin: 0;' : 'max-width: 200px; opacity: 1; margin-left: 0.5rem;'};
`;

const NavSectionTitle = styled.div<{ collapsed: boolean }>`
    ${tw`px-4 pt-4 pb-3 text-[10px] font-bold text-neutral-500 uppercase tracking-widest truncate transition-colors duration-300`};
    ${props => props.collapsed ? tw`text-transparent` : ''};
`;

const SidebarFooter = styled.div<{ collapsed: boolean }>`
    ${tw`p-4 border-t border-neutral-800 text-[10px] text-neutral-500 font-medium transition-all duration-300`};
    ${props => props.collapsed ? tw`text-center px-0` : ''};
`;

const NavItem = styled(NavLink) <{ collapsed?: boolean }>`
    ${tw`flex items-center py-2.5 text-sm text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100`};
    padding-left: 1.25rem;
    padding-right: 1.25rem;
    justify-content: ${props => props.collapsed ? 'center' : 'flex-start'};
    &.active {
        ${tw`bg-neutral-800 text-neutral-100 border-r-2 border-neutral-100`};
        & .icon-container {
            ${tw`text-neutral-100`};
        }
    }
`;

const ExternalNavItem = styled.a<{ collapsed?: boolean }>`
    ${tw`flex items-center py-2.5 text-sm text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100`};
    padding-left: 1.25rem;
    padding-right: 1.25rem;
    justify-content: ${props => props.collapsed ? 'center' : 'flex-start'};
`;

const IconContainer = styled.div<{ collapsed?: boolean }>`
    ${tw`flex items-center justify-center flex-shrink-0 text-neutral-500 transition-colors duration-150`};
    width: 1.25rem;
`;

const SectionTitle = ({ children, collapsed }: { children: React.ReactNode, collapsed: boolean }) => (
    <NavSectionTitle collapsed={collapsed}>{children}</NavSectionTitle>
);

const Divider = styled.div`
    ${tw`mx-4 my-2 border-t border-neutral-800`};
`;

const SidebarScroll = styled.div`
    ${tw`flex-1 overflow-y-auto overflow-x-hidden pb-4`};
    &::-webkit-scrollbar {
        width: 4px;
    }
    &::-webkit-scrollbar-thumb {
        ${tw`bg-neutral-700`};
    }
`;

const ServerLinks = () => {
    const internalId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const eggId = ServerContext.useStoreState((state) => state.server.data?.eggId);
    const rootAdmin = useStoreState((state: any) => state.user.data!.rootAdmin);
    const { t } = useTranslation('strings');
    const isTs3 = eggId === 12;

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
            case 'Startup': return faPlayCircle;
            case 'server.settings':
            case 'Settings': return faCogs;
            case 'server.activity':
            case 'Activity': return faListUl;
            case 'server.addons':
            case 'Addons': return faPuzzlePiece;
            case 'server.firewall':
            case 'Firewall': return faShieldAlt;
            case 'Snapshots': return faCloudUploadAlt;
            case 'Bans': return faShieldAlt;
            case 'Tokens': return faPlug;
            case 'Logs': return faListUl;
            case 'TS3 Viewer': return faExternalLinkAlt;
            case 'Query Terminal': return faTerminal;
            default: return faLayerGroup;
        }
    };

    const collapsed = useStoreState((state) => state.sidebarCollapsed);
    const ts3RouteOrder = [
        '/',
        '/console',
        '/files',
        '/ts3/snapshots',
        '/ts3/bans',
        '/ts3/tokens',
        '/ts3/logs',
        '/ts3/query',
        '/activity',
        '/ts3/html-viewer',
        '/settings',
    ];

    return (
        <>
            <SectionTitle collapsed={collapsed}>{t('nav.server_menu')}</SectionTitle>
            {routes.server
                .filter((route) => (isTs3
                    ? route.path.startsWith('/ts3') || route.path === '/' || route.path === '/activity' || route.path === '/settings' || route.path === '/console' || route.path === '/files'
                    : !route.path.startsWith('/ts3')))
                .filter((route) => !(isTs3 && route.path === '/backups'))
                .filter((route) => !(isTs3 && (route.path === '/console' || route.path === '/files') && !rootAdmin))
                .filter((route) => route.path !== '/ts3/query' || rootAdmin)
                .sort((a, b) => {
                    if (!isTs3) return 0;

                    const aIndex = ts3RouteOrder.indexOf(a.path);
                    const bIndex = ts3RouteOrder.indexOf(b.path);
                    const safeA = aIndex === -1 ? Number.MAX_SAFE_INTEGER : aIndex;
                    const safeB = bIndex === -1 ? Number.MAX_SAFE_INTEGER : bIndex;

                    return safeA - safeB;
                })
                .filter((route) => !!route.name)
                .map((route) => (
                    route.permission ? (
                        <Can key={route.path} action={route.permission as any} matchAny>
                            <NavItem to={to(route.path)} exact={route.exact} collapsed={collapsed} title={route.nameKey ? t(route.nameKey) : route.name!}>
                                <IconContainer className="icon-container">
                                    <FontAwesomeIcon icon={getIcon((route as any).nameKey, route.name!)} />
                                </IconContainer>
                                <NavItemLabel collapsed={collapsed}>{route.nameKey ? t(route.nameKey) : route.name!}</NavItemLabel>
                            </NavItem>
                        </Can>
                    ) : (
                        <NavItem key={route.path} to={to(route.path)} exact={route.exact} collapsed={collapsed} title={route.nameKey ? t(route.nameKey) : route.name!}>
                            <IconContainer className="icon-container">
                                <FontAwesomeIcon icon={getIcon((route as any).nameKey, route.name!)} />
                            </IconContainer>
                            <NavItemLabel collapsed={collapsed}>{route.nameKey ? t(route.nameKey) : route.name!}</NavItemLabel>
                        </NavItem>
                    )
                ))}

            {rootAdmin && internalId && (
                <a
                    href={`/admin/servers/view/${internalId}`}
                    target={'_blank'}
                    rel="noreferrer"
                    title={t('nav.admin_view')}
                    style={{ justifyContent: collapsed ? 'center' : 'flex-start', paddingLeft: '1.25rem', paddingRight: '1.25rem' }}
                    tw="flex items-center py-2.5 text-sm text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100"
                >
                    <IconContainer className="icon-container">
                        <FontAwesomeIcon icon={faExternalLinkAlt} />
                    </IconContainer>
                    <NavItemLabel collapsed={collapsed}>{t('nav.admin_view')}</NavItemLabel>
                </a>
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
            <SidebarScroll>
                <SectionTitle collapsed={collapsed}>{t('nav.navigation')}</SectionTitle>
                <NavItem to={'/'} exact collapsed={collapsed} title={collapsed ? t('nav.dashboard') : undefined}>
                    <IconContainer className="icon-container">
                        <FontAwesomeIcon icon={faLayerGroup} />
                    </IconContainer>
                    <NavItemLabel collapsed={collapsed}>{t('nav.dashboard')}</NavItemLabel>
                </NavItem>

                <ExternalNavItem
                    href={`${CLIENT_AREA_URL}/support`}
                    collapsed={collapsed}
                    title={collapsed ? t('nav.support') : undefined}
                >
                    <IconContainer className="icon-container">
                        <FontAwesomeIcon icon={faLifeRing} />
                    </IconContainer>
                    <NavItemLabel collapsed={collapsed}>{t('nav.support')}</NavItemLabel>
                </ExternalNavItem>

                <ExternalNavItem
                    href={`${CLIENT_AREA_URL}/faturas`}
                    collapsed={collapsed}
                    title={collapsed ? t('nav.invoices') : undefined}
                >
                    <IconContainer className="icon-container">
                        <FontAwesomeIcon icon={faReceipt} />
                    </IconContainer>
                    <NavItemLabel collapsed={collapsed}>{t('nav.invoices')}</NavItemLabel>
                </ExternalNavItem>

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
        </SidebarContainer>
    );
};

export default Sidebar;
