import * as React from 'react';
import { useState } from 'react';
import { Link, NavLink, useRouteMatch } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCogs,
    faLayerGroup,
    faSignOutAlt,
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
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Avatar from '@/components/Avatar';
import Can from '@/components/elements/Can';
import routes from '@/routers/routes';

const SidebarContainer = styled.div`
    ${tw`flex flex-col h-screen bg-neutral-900 shadow-xl border-r border-neutral-800 w-[250px] fixed left-0 top-0 z-50 transition-all duration-300`};
`;

const NavItem = styled(NavLink)`
    ${tw`flex items-center px-4 py-3 text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100`};
    &.active {
        ${tw`bg-neutral-800 text-cyan-400 border-r-4 border-cyan-600`};
    }
`;

const SectionTitle = styled.div`
    ${tw`px-4 pt-6 pb-2 text-xs font-bold text-neutral-500 uppercase tracking-widest`};
`;

const SidebarScroll = styled.div`
    ${tw`flex-1 overflow-y-auto overflow-x-hidden`};
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
            <SectionTitle tw="truncate">Server: {serverName}</SectionTitle>
            {routes.server
                .filter((route) => !!route.name)
                .map((route) => (
                    route.permission ? (
                        <Can key={route.path} action={route.permission as any} matchAny>
                            <NavItem to={to(route.path)} exact={route.exact}>
                                <FontAwesomeIcon icon={getIcon(route.name!)} tw="mr-3 w-5 text-center" />
                                {route.name}
                            </NavItem>
                        </Can>
                    ) : (
                        <NavItem key={route.path} to={to(route.path)} exact={route.exact}>
                            <FontAwesomeIcon icon={getIcon(route.name!)} tw="mr-3 w-5 text-center" />
                            {route.name}
                        </NavItem>
                    )
                ))}

            {rootAdmin && internalId && (
                <a
                    href={`/admin/servers/view/${internalId}`}
                    target={'_blank'}
                    rel="noreferrer"
                    tw="flex items-center px-4 py-3 text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100"
                >
                    <FontAwesomeIcon icon={faExternalLinkAlt} tw="mr-3 w-5 text-center" />
                    Admin View
                </a>
            )}
        </>
    );
};

const Sidebar = () => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data!.name);
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    const match = useRouteMatch<{ id: string }>('/server/:id');

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <SidebarContainer>
            <SpinnerOverlay visible={isLoggingOut} />
            <div tw="p-6">
                <Link to={'/'} tw="text-2xl font-header font-bold text-neutral-100 no-underline block truncate">
                    {name}
                </Link>
            </div>

            <SidebarScroll>
                <SectionTitle>Global</SectionTitle>
                <NavItem to={'/'} exact>
                    <FontAwesomeIcon icon={faLayerGroup} tw="mr-3 w-5 text-center" />
                    Dashboard
                </NavItem>
                <NavItem to={'/account'}>
                    <div tw="mr-3 w-5 flex justify-center">
                        <Avatar.User />
                    </div>
                    Account Settings
                </NavItem>
                {rootAdmin && (
                    <a href={'/admin'} tw="flex items-center px-4 py-3 text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100">
                        <FontAwesomeIcon icon={faCogs} tw="mr-3 w-5 text-center" />
                        Admin Panel
                    </a>
                )}

                {match && (
                    <React.Suspense fallback={null}>
                        <ServerLinks />
                    </React.Suspense>
                )}
            </SidebarScroll>

            <div tw="p-4 border-t border-neutral-800">
                <button onClick={onTriggerLogout} tw="w-full flex items-center px-4 py-2 text-red-500 hover:bg-red-900/20 rounded transition-colors">
                    <FontAwesomeIcon icon={faSignOutAlt} tw="mr-3 w-5" />
                    Sign Out
                </button>
            </div>
        </SidebarContainer>
    );
};

export default Sidebar;
