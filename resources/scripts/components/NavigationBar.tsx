import * as React from 'react';
import { useState, useEffect } from 'react';
import { NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt, faBars, faChevronDown } from '@fortawesome/free-solid-svg-icons';
import { useStoreState, useStoreActions } from '@/state/hooks';
import tw, { theme } from 'twin.macro';
import styled, { css } from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import DropdownMenu from '@/components/elements/DropdownMenu';
import i18n from '@/i18n';
import { useTranslation } from 'react-i18next';
import { getExternalSiteUrl } from '@/lib/externalSite';
import { glassHeaderInner, glassHeaderShell } from '@/assets/css/glassPanel';
import FlagIcon from '@/components/elements/FlagIcon';
import { HEADER_HEIGHT, sidebarLayoutOffset } from '@/lib/sidebarLayout';

const headerNavText = css`
    color: var(--hg-nav-text);
`;

const STORAGE_LNG = 'pterodactyl_lng';
const UI_TO_LNG: Record<string, string> = { US: 'en', BR: 'pt', ES: 'es' };
const LNG_TO_UI: Record<string, { code: string; name: string }> = {
    en: { code: 'US', name: 'English (US)' },
    pt: { code: 'BR', name: 'Português (BR)' },
    es: { code: 'ES', name: 'Español (AR)' },
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

const HeaderBar = styled.div.attrs({ className: 'hg-glass-header' })<{ $collapsed: boolean }>`
    ${glassHeaderShell};
    ${tw`fixed top-0 right-0 z-50`};
    ${(props) => sidebarLayoutOffset(props.$collapsed)};
    transition: left 300ms ease;
`;

const HeaderInner = styled.div`
    ${glassHeaderInner};
    height: ${HEADER_HEIGHT};
`;

const HeaderLeading = styled.div`
    ${tw`flex items-center gap-2 sm:gap-3 px-3 sm:px-4 flex-shrink-0 min-w-0`};
`;

const MenuButton = styled.button`
    ${headerNavText};
    ${tw`flex items-center justify-center w-9 h-9 rounded-md hover:text-neutral-100 hover:bg-neutral-800 transition-all duration-150 flex-shrink-0`};
`;

const RightNavigation = styled.div`
    ${tw`h-full flex items-center flex-shrink-0`};
    & > a,
    & > button,
    & > .navigation-link,
    & > .navigation-link-compact {
        ${headerNavText};
        ${tw`flex items-center h-full no-underline cursor-pointer transition-all duration-150`};
        padding-left: 0.75rem;
        padding-right: 0.75rem;

        @media (min-width: 640px) {
            padding-left: 1.25rem;
            padding-right: 1.25rem;
        }

        @media (min-width: 768px) {
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }

        &:active,
        &:hover,
        &.active {
            ${tw`text-primary-400`};
            box-shadow: inset 0 -2px ${theme`colors.primary.500`.toString()};
        }
    }
`;

export default () => {
    const settings = useStoreState((state: any) => state.settings.data);
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
        { name: LNG_TO_UI.en.name, code: LNG_TO_UI.en.code },
        { name: LNG_TO_UI.pt.name, code: LNG_TO_UI.pt.code },
        { name: LNG_TO_UI.es.name, code: LNG_TO_UI.es.code },
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
            window.location = getExternalSiteUrl(settings);
        });
    };

    return (
        <HeaderBar $collapsed={sidebarCollapsed}>
            <SpinnerOverlay visible={isLoggingOut} fixed />
            <HeaderInner>
                <HeaderLeading>
                    <MenuButton
                        onClick={() => toggleSidebar()}
                        title={sidebarCollapsed ? t('navbar.expand_menu') : t('navbar.collapse_menu')}
                    >
                        <FontAwesomeIcon icon={faBars} />
                    </MenuButton>
                </HeaderLeading>
                <div css={tw`flex-1 min-w-0`} />
                <RightNavigation>
                    <Tooltip placement={'bottom'} content={t('navbar.dashboard')}>
                        <NavLink to={'/'} exact css={tw`hidden md:flex`}>
                            <FontAwesomeIcon icon={faLayerGroup} />
                        </NavLink>
                    </Tooltip>
                    {rootAdmin && (
                        <Tooltip placement={'bottom'} content={t('navbar.admin')}>
                            <a href={'/admin'} rel={'noreferrer'} css={tw`hidden md:flex`}>
                                <FontAwesomeIcon icon={faCogs} />
                            </a>
                        </Tooltip>
                    )}

                    {rootAdmin && (
                        <MenuWrapper className={'navigation-link'} css={tw`hidden sm:flex`} style={{ padding: 0 }}>
                            <DropdownMenu
                                renderToggle={(onClick) => (
                                    <div
                                        onClick={onClick}
                                        className={'flex items-center h-full px-3 sm:px-6 cursor-pointer'}
                                    >
                                        <FlagIcon code={language.code} alt={language.name} size="header" className="mr-2" />
                                        <span className={'text-xs font-bold tracking-wide'}>{language.code}</span>
                                        <FontAwesomeIcon icon={faChevronDown} className={'ml-2 text-[10px]'} />
                                    </div>
                                )}
                            >
                                {languages.map((lang) => (
                                    <Row
                                        key={lang.code}
                                        icon={<FlagIcon code={lang.code} alt={lang.name} size="sm" />}
                                        title={lang.name}
                                        $active={language.code === lang.code}
                                        onClick={() => setLanguage(lang)}
                                    />
                                ))}
                            </DropdownMenu>
                        </MenuWrapper>
                    )}

                    <Tooltip placement={'bottom'} content={t('navbar.sign_out')}>
                        <button onClick={onTriggerLogout}>
                            <FontAwesomeIcon icon={faSignOutAlt} />
                        </button>
                    </Tooltip>
                </RightNavigation>
            </HeaderInner>
        </HeaderBar>
    );
};
