import React, { useState } from 'react';
import useSWR from 'swr';
import { useLocation } from 'react-router';
import { XIcon } from '@heroicons/react/outline';
import tw from 'twin.macro';
import ContentContainer from '@/components/elements/ContentContainer';
import { Alert } from '@/components/elements/alert';
import getPanelAlerts, { PanelAlert, PanelAlertPlacement } from '@/api/getPanelAlerts';

// Shared with the admin area (see partials/admin/panel-alerts.blade.php).
const DISMISSED_KEY = 'panel_alerts_dismissed';

type Dismissed = Record<string, string>;

const readDismissed = (): Dismissed => {
    try {
        return JSON.parse(localStorage.getItem(DISMISSED_KEY) ?? '{}') || {};
    } catch {
        return {};
    }
};

const isOnServerPage = (alert: PanelAlert, pathname: string): boolean => {
    if (alert.serverPages.length === 0) {
        return true;
    }

    const path = pathname.replace(/^\/server\/[^/]+/, '').replace(/\/+$/, '') || '/';

    return alert.serverPages.some((page) => path === page || (page !== '/' && path.startsWith(`${page}/`)));
};

export default ({ serverUuid }: { serverUuid?: string }) => {
    const { pathname } = useLocation();
    const [dismissed, setDismissed] = useState<Dismissed>(readDismissed);
    const { data: alerts } = useSWR<PanelAlert[]>(['panel:alerts', serverUuid], () => getPanelAlerts(serverUuid), {
        dedupingInterval: 30000,
        errorRetryCount: 1,
    });

    let placement: PanelAlertPlacement = 'dashboard';
    if (serverUuid) {
        placement = 'server';
    } else if (pathname.startsWith('/account')) {
        placement = 'account';
    }

    const visible = (alerts ?? []).filter(
        (alert) =>
            alert.placements.includes(placement) &&
            (!serverUuid || isOnServerPage(alert, pathname)) &&
            !(alert.dismissible && dismissed[alert.id] === alert.revision)
    );

    if (visible.length === 0) {
        return null;
    }

    const dismiss = (alert: PanelAlert) => {
        const next = { ...readDismissed(), [alert.id]: alert.revision };
        setDismissed(next);
        try {
            localStorage.setItem(DISMISSED_KEY, JSON.stringify(next));
        } catch {
            // The alert just comes back on the next page load.
        }
    };

    return (
        <ContentContainer css={tw`w-full min-w-0 mt-4 mb-4 space-y-2`}>
            {visible.map((alert) => (
                <Alert key={alert.id} type={alert.type} className={'text-sm'}>
                    <div className={'flex-1 min-w-0 break-words'}>
                        {alert.title && <p className={'font-semibold'}>{alert.title}</p>}
                        {/* Rendered by PanelAlert::renderedMessage(), which escapes HTML and drops unsafe links. */}
                        <p
                            className={'[&_a]:underline [&_a]:font-medium'}
                            dangerouslySetInnerHTML={{ __html: alert.messageHtml }}
                        />
                    </div>
                    {alert.dismissible && (
                        <button
                            type={'button'}
                            aria-label={'Fechar'}
                            className={'ml-3 flex-shrink-0 text-gray-300 hover:text-gray-50'}
                            onClick={() => dismiss(alert)}
                        >
                            <XIcon className={'w-4 h-4'} />
                        </button>
                    )}
                </Alert>
            ))}
        </ContentContainer>
    );
};
