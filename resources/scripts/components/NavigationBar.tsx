import * as React from 'react';
import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt, faBars } from '@fortawesome/free-solid-svg-icons';
import { useStoreState, useStoreActions } from '@/state/hooks';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import tw, { theme } from 'twin.macro';
import styled from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Avatar from '@/components/Avatar';

const RightNavigation = styled.div`
    & > a,
    & > button,
    & > .navigation-link {
        ${tw`flex items-center h-full no-underline text-neutral-300 px-6 cursor-pointer transition-all duration-150`};

        &:active,
        &:hover {
            ${tw`text-neutral-100 bg-black`};
        }

        &:active,
        &:hover,
        &.active {
            box-shadow: inset 0 -2px ${theme`colors.cyan.600`.toString()};
        }
    }
`;

export default () => {
    const name = useStoreState((state: any) => state.settings.data!.name);
    const rootAdmin = useStoreState((state: any) => state.user.data!.rootAdmin);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const toggleSidebar = useStoreActions((actions) => actions.toggleSidebar);
    const sidebarCollapsed = useStoreState((state: any) => state.sidebarCollapsed);

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <div className={'w-full bg-neutral-900 shadow-md overflow-x-auto fixed top-0 z-50'}>
            <SpinnerOverlay visible={isLoggingOut} />
            <div className={'w-full flex items-center h-[3.5rem] px-4'}>
                {/* Hamburger – fixed width matching sidebar, aligns with sidebar left edge */}
                <div
                    style={{ width: sidebarCollapsed ? '70px' : '240px', minWidth: sidebarCollapsed ? '70px' : '240px' }}
                    className={'flex items-center justify-center transition-all duration-300'}
                >
                    <button
                        onClick={() => toggleSidebar()}
                        className={'flex items-center justify-center w-9 h-9 rounded-md text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800 transition-all duration-150'}
                        title={sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'}
                    >
                        <FontAwesomeIcon icon={faBars} />
                    </button>
                </div>
                <div id={'logo'} className={'flex items-center flex-1'}>
                    <Link
                        to={'/'}
                        className={
                            'text-2xl font-header font-medium px-2 no-underline text-neutral-200 hover:text-neutral-100 transition-colors duration-150'
                        }
                    >
                        {name}
                    </Link>
                </div>
                <RightNavigation className={'flex h-full items-center justify-center'}>
                    <SearchContainer />
                    <Tooltip placement={'bottom'} content={'Dashboard'}>
                        <NavLink to={'/'} exact>
                            <FontAwesomeIcon icon={faLayerGroup} />
                        </NavLink>
                    </Tooltip>
                    {rootAdmin && (
                        <Tooltip placement={'bottom'} content={'Admin'}>
                            <a href={'/admin'} rel={'noreferrer'}>
                                <FontAwesomeIcon icon={faCogs} />
                            </a>
                        </Tooltip>
                    )}
                    <Tooltip placement={'bottom'} content={'Account Settings'}>
                        <NavLink to={'/account'}>
                            <span className={'flex items-center w-5 h-5'}>
                                <Avatar.User />
                            </span>
                        </NavLink>
                    </Tooltip>
                    <Tooltip placement={'bottom'} content={'Sign Out'}>
                        <button onClick={onTriggerLogout}>
                            <FontAwesomeIcon icon={faSignOutAlt} />
                        </button>
                    </Tooltip>
                </RightNavigation>
            </div>
        </div>
    );
};
