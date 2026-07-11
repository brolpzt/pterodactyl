import React, { lazy } from 'react';
import { hot } from 'react-hot-loader/root';
import { Route, Router, Switch } from 'react-router-dom';
import { StoreProvider } from 'easy-peasy';
import { store } from '@/state';
import { SiteSettings } from '@/state/settings';
import ProgressBar from '@/components/elements/ProgressBar';
import { DocumentOverlayScrollbar } from '@/components/elements/OverlayScrollbar';
import { NotFound } from '@/components/elements/ScreenBlock';
import tw from 'twin.macro';
import GlobalStylesheet from '@/assets/css/GlobalStylesheet';
import { history } from '@/components/history';
import { setupInterceptors } from '@/api/interceptors';
import AuthenticatedRoute from '@/components/elements/AuthenticatedRoute';
import { ServerContext } from '@/state/server';
import '@/assets/tailwind.css';
import Spinner from '@/components/elements/Spinner';
import { SwitchTransition } from 'react-transition-group';
import Fade from '@/components/elements/Fade';

const DashboardRouter = lazy(() => import(/* webpackChunkName: "dashboard" */ '@/routers/DashboardRouter'));
const ServerRouter = lazy(() => import(/* webpackChunkName: "server" */ '@/routers/ServerRouter'));
const AuthenticationRouter = lazy(() => import(/* webpackChunkName: "auth" */ '@/routers/AuthenticationRouter'));

interface ExtendedWindow extends Window {
    SiteConfiguration?: SiteSettings;
    PterodactylUser?: {
        uuid: string;
        username: string;
        email: string;
        /* eslint-disable camelcase */
        root_admin: boolean;
        use_totp: boolean;
        language: string;
        updated_at: string;
        created_at: string;
        /* eslint-enable camelcase */
    };
}

setupInterceptors(history);

const App = () => {
    const { PterodactylUser, SiteConfiguration } = window as ExtendedWindow;
    if (PterodactylUser && !store.getState().user.data) {
        store.getActions().user.setUserData({
            uuid: PterodactylUser.uuid,
            username: PterodactylUser.username,
            email: PterodactylUser.email,
            language: PterodactylUser.language,
            rootAdmin: PterodactylUser.root_admin,
            useTotp: PterodactylUser.use_totp,
            createdAt: new Date(PterodactylUser.created_at),
            updatedAt: new Date(PterodactylUser.updated_at),
        });
    }

    if (!store.getState().settings.data) {
        store.getActions().settings.setSettings(SiteConfiguration!);
    }

    return (
        <>
            <GlobalStylesheet />
            <DocumentOverlayScrollbar />
            <StoreProvider store={store}>
                <ProgressBar />
                <div css={tw`mx-auto w-auto`}>
                    <Router history={history}>
                        {/* Route gives us location so SwitchTransition can key on route changes */}
                        <Route render={({ location }) => {
                            // Use a stable key: all server sub-pages share 'server' so only the
                            // dashboard→server boundary triggers a fade, not every sub-page click.
                            const topKey = location.pathname.startsWith('/server/') ? 'server' : location.pathname;
                            return (
                                <SwitchTransition>
                                    <Fade timeout={150} key={topKey} in appear unmountOnExit>
                                        <div>
                                            <Switch location={location}>
                                                <Route path={'/auth'}>
                                                    <Spinner.Suspense>
                                                        <AuthenticationRouter />
                                                    </Spinner.Suspense>
                                                </Route>
                                                <AuthenticatedRoute path={'/server/:id'}>
                                                    <ServerContext.Provider>
                                                        <Spinner.Suspense>
                                                            <ServerRouter />
                                                        </Spinner.Suspense>
                                                    </ServerContext.Provider>
                                                </AuthenticatedRoute>
                                                <AuthenticatedRoute path={'/'}>
                                                    <Spinner.Suspense>
                                                        <DashboardRouter />
                                                    </Spinner.Suspense>
                                                </AuthenticatedRoute>
                                                <Route path={'*'}>
                                                    <NotFound />
                                                </Route>
                                            </Switch>
                                        </div>
                                    </Fade>
                                </SwitchTransition>
                            );
                        }} />
                    </Router>
                </div>
            </StoreProvider>
        </>
    );
};

export default hot(App);
