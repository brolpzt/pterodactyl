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
} from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { ServerContext } from '@/state/server';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import Can from '@/components/elements/Can';
import routes from '@/routers/routes';

const SidebarContainer = styled.div`
    ${tw`flex flex-col bg-neutral-900 shadow-md border-r border-neutral-800 w-[240px] fixed left-0 bottom-0 z-40 transition-all duration-300`};
    top: 3.5rem;
`;

const NavItem = styled(NavLink)`
    ${tw`flex items-center px-4 py-2.5 text-sm text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100`};
    &.active {
        ${tw`bg-neutral-800 text-neutral-100 border-r-2 border-neutral-100`};
        & .icon-container {
            ${tw`text-neutral-100`};
        }
    }
`;

const IconContainer = styled.div`
    ${tw`flex items-center justify-center w-8 mr-2 flex-shrink-0 text-neutral-500 transition-colors duration-150`};
`;

const SectionTitle = styled.div`
    ${tw`px-4 pt-4 pb-1 text-[10px] font-bold text-neutral-500 uppercase tracking-widest`};
`;

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

    return (
        <>
            <SectionTitle>Menu Rápido</SectionTitle>
            <NavItem to={to('/quick/add-admin')} onClick={(e) => e.preventDefault()}>
                <IconContainer className="icon-container" tw="text-green-500">
                    <FontAwesomeIcon icon={faUserPlus} />
                </IconContainer>
                Adicionar admin
            </NavItem>
            <NavItem to={to('/quick/amxx')} onClick={(e) => e.preventDefault()}>
                <IconContainer className="icon-container" tw="text-yellow-500">
                    <FontAwesomeIcon icon={faPlug} />
                </IconContainer>
                Plugins AMXX
            </NavItem>
            <NavItem to={to('/quick/metamod')} onClick={(e) => e.preventDefault()}>
                <IconContainer className="icon-container" tw="text-red-500">
                    <FontAwesomeIcon icon={faMicrochip} />
                </IconContainer>
                Plugins Metamod
            </NavItem>

            <SectionTitle>Menu do Servidor</SectionTitle>
            {routes.server
                .filter((route) => !!route.name)
                .map((route) => (
                    route.permission ? (
                        <Can key={route.path} action={route.permission as any} matchAny>
                            <NavItem to={to(route.path)} exact={route.exact}>
                                <IconContainer className="icon-container">
                                    <FontAwesomeIcon icon={getIcon(route.name!)} />
                                </IconContainer>
                                {route.name}
                            </NavItem>
                        </Can>
                    ) : (
                        <NavItem key={route.path} to={to(route.path)} exact={route.exact}>
                            <IconContainer className="icon-container">
                                <FontAwesomeIcon icon={getIcon(route.name!)} />
                            </IconContainer>
                            {route.name}
                        </NavItem>
                    )
                ))}

            {rootAdmin && internalId && (
                <a
                    href={`/admin/servers/view/${internalId}`}
                    target={'_blank'}
                    rel="noreferrer"
                    tw="flex items-center px-4 py-2.5 text-sm text-neutral-400 no-underline transition-all duration-150 hover:bg-neutral-800 hover:text-neutral-100"
                >
                    <IconContainer className="icon-container">
                        <FontAwesomeIcon icon={faExternalLinkAlt} />
                    </IconContainer>
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
                <SectionTitle>Navigation</SectionTitle>
                <NavItem to={'/'} exact>
                    <IconContainer className="icon-container">
                        <FontAwesomeIcon icon={faLayerGroup} />
                    </IconContainer>
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
