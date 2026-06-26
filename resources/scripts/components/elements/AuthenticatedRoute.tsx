import React, { useEffect } from 'react';
import { Route, RouteProps } from 'react-router';
import { useStoreState } from '@/state/hooks';
import { redirectToExternalSite } from '@/lib/externalSite';

export default ({ children, ...props }: Omit<RouteProps, 'render'>) => {
    const isAuthenticated = useStoreState((state) => !!state.user.data?.uuid);
    const settings = useStoreState((state) => state.settings.data);

    useEffect(() => {
        if (!isAuthenticated) {
            redirectToExternalSite(settings);
        }
    }, [isAuthenticated, settings]);

    return (
        <Route
            {...props}
            render={() => (isAuthenticated ? children : null)}
        />
    );
};
