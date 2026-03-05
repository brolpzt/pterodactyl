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
} from '@fortawesome/free-solid-svg-icons';
import { useStoreState, useStoreActions } from '@/state/hooks';
import { ApplicationStore } from '@/state';
import { ServerContext } from '@/state/server';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import Can from '@/components/elements/Can';
import routes from '@/routers/routes';

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
    const rootAdmin = useStoreState((state: any) => state.user.data!.rootAdmin);

    const match = useRouteMatch<{ id: string }>('/server/:id');

    const to = (value: string) => {
        if (!match) return '/';
        const url = `/server/${match.params.id}`;
        if (value === '/') return url;
        return `${url.replace(/\/*$/, '')}/${value.replace(/^\/+/, '')}`;
    };

    const getIcon = (routeName: string) => {
        switch (routeName) {
            case 'Console': return faTerminal;
            case 'Files': return faFolderOpen;
            case 'Databases': return faDatabase;
            case 'Schedules': return faCalendarAlt;
            case 'Users': return faUsers;
            case 'Backups': return faCloudUploadAlt;
            case 'Network': return faNetworkWired;
            case 'Startup': return faPlayCircle;
            case 'Settings': return faCogs;
            case 'Activity': return faListUl;
            case 'Addons': return faPuzzlePiece;
            case 'Firewall': return faShieldAlt;
            default: return faLayerGroup;
        }
    };

    const collapsed = useStoreState((state) => state.sidebarCollapsed);

    return (
        <>
            <SectionTitle collapsed={collapsed}>Menu do Servidor</SectionTitle>
            {routes.server
                .filter((route) => !!route.name)
                .map((route) => (
                    route.permission ? (
                        <Can key={route.path} action={route.permission as any} matchAny>
                            <NavItem to={to(route.path)} exact={route.exact} collapsed={collapsed} title={route.name}>
                                <IconContainer className="icon-container">
                                    <FontAwesomeIcon icon={getIcon(route.name!)} />
                                </IconContainer>
                                <NavItemLabel collapsed={collapsed}>{route.name}</NavItemLabel>
                            </NavItem>
                        </Can>
                    ) : (
                        <NavItem key={route.path} to={to(route.path)} exact={route.exact} collapsed={collapsed} title={route.name}>
                            <IconContainer className="icon-container">
                                <FontAwesomeIcon icon={getIcon(route.name!)} />
                            </IconContainer>
                            <NavItemLabel collapsed={collapsed}>{route.name}</NavItemLabel>
                        </NavItem>
                    )
                ))}

            {rootAdmin && internalId && (
                <a
                    href={`/admin/servers/view/${internalId}`}
                    target={'_blank'}
                    rel="noreferrer"
                    title={'Admin View'}
                    style={{ justifyContent: collapsed ? 'center' : 'flex-start', paddingLeft: '1.25rem', paddingRight: '1.25rem' }}
                    tw="flex items-center py-2.5 text-sm text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100"
                >
                    <IconContainer className="icon-container">
                        <FontAwesomeIcon icon={faExternalLinkAlt} />
                    </IconContainer>
                    <NavItemLabel collapsed={collapsed}>Admin View</NavItemLabel>
                </a>
            )}
        </>
    );
};

const Sidebar = () => {
    const match = useRouteMatch<{ id: string }>('/server/:id');
    const collapsed = useStoreState((state) => state.sidebarCollapsed);
    const toggleSidebar = useStoreActions((actions) => actions.toggleSidebar);

    return (
        <SidebarContainer collapsed={collapsed}>
            <SidebarScroll>
                <SectionTitle collapsed={collapsed}>Navigation</SectionTitle>
                <NavItem to={'/'} exact collapsed={collapsed} title={collapsed ? 'Dashboard' : undefined}>
                    <IconContainer className="icon-container">
                        <FontAwesomeIcon icon={faLayerGroup} />
                    </IconContainer>
                    <NavItemLabel collapsed={collapsed}>Dashboard</NavItemLabel>
                </NavItem>

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
