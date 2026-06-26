import React from 'react';
import { NavLink, Route, Switch } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import NavigationBar from '@/components/NavigationBar';
import DashboardContainer from '@/components/dashboard/DashboardContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import TransitionRouter from '@/TransitionRouter';
import SubNavigation from '@/components/elements/SubNavigation';
import { useLocation } from 'react-router';
import Spinner from '@/components/elements/Spinner';
import routes from '@/routers/routes';

import Sidebar from '@/components/Sidebar';
import tw from 'twin.macro';

export default () => {
    const location = useLocation();
    const sidebarCollapsed = useStoreState((state: any) => state.sidebarCollapsed);
    const rootAdmin = useStoreState((state: any) => state.user.data!.rootAdmin);

    return (
        <>
            <NavigationBar />
            <div css={tw`flex min-h-screen pt-[3.5rem]`}>
                <Sidebar />
                <div
                    css={tw`flex-1 bg-neutral-800 transition-all duration-300`}
                    style={{ marginLeft: sidebarCollapsed ? '70px' : '240px' }}
                >
                    {location.pathname.startsWith('/account') && (
                        <SubNavigation>
                            <div>
                                {routes.account
                                    .filter((route) => !!route.name && (!route.adminOnly || rootAdmin))
                                    .map(({ path, name, exact = false }) => (
                                        <NavLink key={path} to={`/account/${path}`.replace('//', '/')} exact={exact}>
                                            {name}
                                        </NavLink>
                                    ))}
                            </div>
                        </SubNavigation>
                    )}
                    <TransitionRouter>
                        <React.Suspense fallback={<Spinner centered />}>
                            <Switch location={location}>
                                <Route path={'/'} exact>
                                    <DashboardContainer />
                                </Route>
                                {routes.account
                                    .filter((route) => !route.adminOnly || rootAdmin)
                                    .map(({ path, component: Component }) => (
                                        <Route key={path} path={`/account/${path}`.replace('//', '/')} exact>
                                            <Component />
                                        </Route>
                                    ))}
                                <Route path={'*'}>
                                    <NotFound />
                                </Route>
                            </Switch>
                        </React.Suspense>
                    </TransitionRouter>
                </div>
            </div>
        </>
    );
};
