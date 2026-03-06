import * as React from 'react';
import { useState, useEffect } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt, faBars, faLanguage, faChevronDown, faWallet } from '@fortawesome/free-solid-svg-icons';
import { useStoreState, useStoreActions } from '@/state/hooks';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import tw, { theme } from 'twin.macro';
import styled from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import DropdownMenu from '@/components/elements/DropdownMenu';
import Avatar from '@/components/Avatar';
import { getBillingInfo } from '@/api/account/billing';
import { useCurrency, CURRENCIES } from '@/context/CurrencyContext';

const StyledRow = styled.div<{ $active?: boolean }>`
    ${tw`p-2 flex items-center rounded cursor-pointer text-sm`};
    ${(props) =>
        props.$active ? tw`bg-neutral-100 text-neutral-700 font-bold` : tw`hover:bg-neutral-100 hover:text-neutral-700 text-neutral-500`};
`;

interface RowProps extends React.HTMLAttributes<HTMLDivElement> {
    icon?: any;
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
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const toggleSidebar = useStoreActions((actions) => actions.toggleSidebar);
    const sidebarCollapsed = useStoreState((state: any) => state.sidebarCollapsed);
    const [language, setLanguage] = useState({ code: 'BR', flag: '🇧🇷' });
    const { currency, setCurrency, formatPrice } = useCurrency();
    const [walletBalance, setWalletBalance] = useState<number | null>(null);

    useEffect(() => {
        if (!rootAdmin) return;
        getBillingInfo()
            .then((data) => setWalletBalance(data.balance))
            .catch(() => setWalletBalance(null));
    }, [rootAdmin]);

    const languages = [
        { name: 'English (US)', code: 'US', flag: '🇺🇸' },
        { name: 'Português (BR)', code: 'BR', flag: '🇧🇷' },
        { name: 'Español (AR)', code: 'ES', flag: '🇦🇷' },
    ];


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
                {/* Logo area — exactly matches sidebar width */}
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
                {/* Hamburger — placed right after the sidebar boundary (20px gap into content) */}
                <button
                    onClick={() => toggleSidebar()}
                    className={'flex items-center justify-center w-9 h-9 rounded-md text-neutral-400 hover:text-neutral-100 hover:bg-neutral-800 transition-all duration-150 flex-shrink-0'}
                    title={sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'}
                >
                    <FontAwesomeIcon icon={faBars} />
                </button>
                {/* Spacer */}
                <div className={'flex-1'} />
                <RightNavigation className={'flex h-full items-center justify-center'}>
                    {rootAdmin && (
                        <Tooltip placement={'bottom'} content={'Account Balance'}>
                            <NavLink to={'/account/billing'} className={'!px-4'}>
                                <div className={'flex items-center bg-neutral-800 rounded px-3 py-1.5 border border-neutral-700 hover:border-cyan-500 transition-colors'}>
                                    <FontAwesomeIcon icon={faWallet} className={'text-cyan-400 mr-2'} />
                                    <span className={'font-mono text-sm font-semibold'}>
                                        {walletBalance !== null ? formatPrice(walletBalance) : '—'}
                                    </span>
                                </div>
                            </NavLink>
                        </Tooltip>
                    )}
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

                    {rootAdmin && (
                        <>
                            <MenuWrapper className={'navigation-link'} style={{ padding: 0 }}>
                                <DropdownMenu
                                    renderToggle={(onClick) => (
                                        <div
                                            onClick={onClick}
                                            className={'flex items-center h-full px-6 cursor-pointer'}
                                        >
                                            <span className={'text-xs font-bold tracking-wide mr-2'}>{currency.symbol}</span>
                                            <span className={'text-xs font-bold tracking-wide'}>{currency.code}</span>
                                            <FontAwesomeIcon icon={faChevronDown} className={'ml-2 text-[10px]'} />
                                        </div>
                                    )}
                                >
                                    {CURRENCIES.map((curr) => (
                                        <Row
                                            key={curr.code}
                                            icon={<span css={tw`font-mono`}>{curr.symbol}</span>}
                                            title={curr.name}
                                            $active={currency.code === curr.code}
                                            onClick={() => setCurrency(curr)}
                                        />
                                    ))}
                                </DropdownMenu>
                            </MenuWrapper>

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
                        </>
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
