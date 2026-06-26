import * as React from 'react';
import { useState, useEffect } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt, faBars, faChevronDown } from '@fortawesome/free-solid-svg-icons';
import { useStoreState, useStoreActions } from '@/state/hooks';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import tw, { theme } from 'twin.macro';
import styled from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import DropdownMenu from '@/components/elements/DropdownMenu';
import Avatar from '@/components/Avatar';
import i18n from '@/i18n';
import { useTranslation } from 'react-i18next';

const STORAGE_LNG = 'pterodactyl_lng';
const UI_TO_LNG: Record<string, string> = { US: 'en', BR: 'pt', ES: 'es' };
const LNG_TO_UI: Record<string, { code: string; name: string; flag: string }> = {
    en: { code: 'US', name: 'English (US)', flag: '🇺🇸' },
    pt: { code: 'BR', name: 'Português (BR)', flag: '🇧🇷' },
    es: { code: 'ES', name: 'Español (AR)', flag: '🇦🇷' },
};

const StyledRow = styled.div<{ $active?: boolean }>`
    ${tw`p-2 flex items-center rounded cursor-pointer text-sm`};
    ${(props) =>
        props.$active ? tw`bg-neutral-100 text-neutral-700 font-bold` : tw`hover:bg-neutral-100 hover:text-neutral-700 text-neutral-500`};
`;

interface RowProps extends React.HTMLAttributes<HTMLDivElement> {
    icon?: React.ReactNode;
    title: string;
    $active?: boolean;
}

const Row = ({ icon, title, ...props }: RowProps) => (
    <StyledRow {...props}>
        {icon && <span css={tw`text-base w-5 flex items-center justify-center`}>{icon}</span>}
        <span css={tw`ml-2`}>{title}</span>
    </StyledRow>
);

const MenuWrapper = styled.div`
    ${tw`h-full flex items-center`};
    & > div {
        ${tw`h-full`};
    }
`;

const RightNavigation = styled.div`
    & > a,
    & > button,
    & > .navigation-link {
        ${tw`flex items-center h-full no-underline text-neutral-300 px-6 cursor-pointer transition-all duration-150`};

        &:active,
        &:hover {
            ${tw`text-neutral-100 bg-black transition-all duration-150`};
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
    const { t } = useTranslation('strings');
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const toggleSidebar = useStoreActions((actions) => actions.toggleSidebar);
    const sidebarCollapsed = useStoreState((state: any) => state.sidebarCollapsed);
    const [language, setLanguage] = useState(() => {
        const lng = i18n.language?.split('-')[0] || 'en';
        return LNG_TO_UI[lng] || LNG_TO_UI.en;
    });

    const languages = [
        { name: LNG_TO_UI.en.name, code: 'US', flag: LNG_TO_UI.en.flag },
        { name: LNG_TO_UI.pt.name, code: 'BR', flag: LNG_TO_UI.pt.flag },
        { name: LNG_TO_UI.es.name, code: 'ES', flag: LNG_TO_UI.es.flag },
    ];

    useEffect(() => {
        const lng = UI_TO_LNG[language.code] || 'en';
        if (i18n.language !== lng) {
            i18n.changeLanguage(lng);
            window.localStorage?.setItem(STORAGE_LNG, lng);
        }
    }, [language.code]);

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <div className={'w-full bg-neutral-900 shadow-md fixed top-0 z-50'}>
            <SpinnerOverlay visible={isLoggingOut} fixed />
            <div className={'w-full flex items-center h-[3.5rem]'}>
                <div
                    style={{ width: sidebarCollapsed ? '70px' : '240px', minWidth: sidebarCollapsed ? '70px' : '240px' }}
                    className={'flex items-center px-4 transition-all duration-300 flex-shrink-0'}
                >
                    {!sidebarCollapsed && (
                        <Link
                            to={'/'}
                            className={'text-2xl font-header font-medium no-underline text-neutral-200 hover:text-neutral-100 transition-colors duration-150 whitespace-nowrap'}
                        >
                            {name}
                        </Link>
                    )}
                </div>
                <button
                    onClick={() => toggleSidebar()}
                    className={'flex items-center justify-center w-9 h-9 rounded-md text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800 transition-all duration-150 flex-shrink-0'}
                    title={sidebarCollapsed ? t('navbar.expand_menu') : t('navbar.collapse_menu')}
                >
                    <FontAwesomeIcon icon={faBars} />
                </button>
                <div className={'flex-1'} />
                <RightNavigation className={'flex h-full items-center justify-center'}>
                    <SearchContainer />
                    <Tooltip placement={'bottom'} content={t('navbar.dashboard')}>
                        <NavLink to={'/'} exact>
                            <FontAwesomeIcon icon={faLayerGroup} />
                        </NavLink>
                    </Tooltip>
                    {rootAdmin && (
                        <Tooltip placement={'bottom'} content={t('navbar.admin')}>
                            <a href={'/admin'} rel={'noreferrer'}>
                                <FontAwesomeIcon icon={faCogs} />
                            </a>
                        </Tooltip>
                    )}

                    {rootAdmin && (
                        <MenuWrapper className={'navigation-link'} style={{ padding: 0 }}>
                            <DropdownMenu
                                renderToggle={(onClick) => (
                                    <div
                                        onClick={onClick}
                                        className={'flex items-center h-full px-6 cursor-pointer'}
                                    >
                                        <span className={'mr-2 text-base'}>{language.flag}</span>
                                        <span className={'text-xs font-bold tracking-wide'}>{language.code}</span>
                                        <FontAwesomeIcon icon={faChevronDown} className={'ml-2 text-[10px]'} />
                                    </div>
                                )}
                            >
                                {languages.map((lang) => (
                                    <Row
                                        key={lang.code}
                                        icon={lang.flag}
                                        title={lang.name}
                                        $active={language.code === lang.code}
                                        onClick={() => setLanguage(lang)}
                                    />
                                ))}
                            </DropdownMenu>
                        </MenuWrapper>
                    )}

                    <Tooltip placement={'bottom'} content={t('navbar.account_settings')}>
                        <NavLink to={'/account'}>
                            <span className={'flex items-center w-5 h-5'}>
                                <Avatar.User />
                            </span>
                        </NavLink>
                    </Tooltip>
                    <Tooltip placement={'bottom'} content={t('navbar.sign_out')}>
                        <button onClick={onTriggerLogout}>
                            <FontAwesomeIcon icon={faSignOutAlt} />
                        </button>
                    </Tooltip>
                </RightNavigation>
            </div>
        </div>
    );
};
