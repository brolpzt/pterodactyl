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
} from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { ServerContext } from '@/state/server';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import Can from '@/components/elements/Can';
import routes from '@/routers/routes';

const SidebarContainer = styled.div`
    ${tw`flex flex-col bg-neutral-900 shadow-xl border-r border-neutral-800 w-[240px] fixed left-0 bottom-0 z-40 transition-all duration-300`};
    top: 3.5rem; /* Height of NavigationBar */
`;

const NavItem = styled(NavLink)`
    ${tw`flex items-center px-4 py-2 text-sm text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100`};
    &.active {
        ${tw`bg-neutral-800 text-cyan-400 border-r-2 border-cyan-600`};
    }
`;

const SectionTitle = styled.div`
    ${tw`px-4 pt-6 pb-2 text-[10px] font-bold text-neutral-500 uppercase tracking-widest`};
`;

const ServerHeader = styled.div`
    ${tw`mx-3 mt-4 mb-2 p-3 bg-cyan-900/20 border border-cyan-700/30 rounded-lg`};
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
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name);
    const internalId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);

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

    if (!serverName) return null;

    return (
        <>
            <ServerHeader>
                <div tw="text-[10px] text-cyan-500 font-bold uppercase tracking-wider mb-1">Active Server</div>
                <div tw="text-sm font-bold text-neutral-100 truncate">{serverName}</div>
            </ServerHeader>

            {routes.server
                .filter((route) => !!route.name)
                .map((route) => (
                    route.permission ? (
                        <Can key={route.path} action={route.permission as any} matchAny>
                            <NavItem to={to(route.path)} exact={route.exact}>
                                <FontAwesomeIcon icon={getIcon(route.name!)} tw="mr-3 w-4 text-center text-xs" />
                                {route.name}
                            </NavItem>
                        </Can>
                    ) : (
                        <NavItem key={route.path} to={to(route.path)} exact={route.exact}>
                            <FontAwesomeIcon icon={getIcon(route.name!)} tw="mr-3 w-4 text-center text-xs" />
                            {route.name}
                        </NavItem>
                    )
                ))}

            {rootAdmin && internalId && (
                <a
                    href={`/admin/servers/view/${internalId}`}
                    target={'_blank'}
                    rel="noreferrer"
                    tw="flex items-center px-4 py-2 text-sm text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100"
                >
                    <FontAwesomeIcon icon={faExternalLinkAlt} tw="mr-3 w-4 text-center text-xs" />
                    Admin View
                </a>
            )}
        </>
    );
};

const Sidebar = () => {
    const match = useRouteMatch<{ id: string }>('/server/:id');

    return (
        <SidebarContainer>
            <SidebarScroll>
                <SectionTitle>Main Menu</SectionTitle>
                <NavItem to={'/'} exact>
                    <FontAwesomeIcon icon={faLayerGroup} tw="mr-3 w-4 text-center text-xs" />
                    Dashboard
                </NavItem>

                {match && (
                    <React.Suspense fallback={null}>
                        <ServerLinks />
                    </React.Suspense>
                )}
            </SidebarScroll>
        </SidebarContainer>
    );
};

export default Sidebar;
