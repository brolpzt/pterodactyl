import http from '@/api/http';

export type PanelAlertType = 'info' | 'success' | 'warning' | 'danger';
export type PanelAlertPlacement = 'dashboard' | 'account' | 'server' | 'admin';

export interface PanelAlert {
    id: number;
    title: string | null;
    messageHtml: string;
    type: PanelAlertType;
    dismissible: boolean;
    placements: PanelAlertPlacement[];
    serverPages: string[];
    revision: string;
}

/* eslint-disable camelcase */
interface PanelAlertResponse {
    attributes: {
        id: number;
        title: string | null;
        message_html: string;
        type: PanelAlertType;
        dismissible: boolean;
        placements: PanelAlertPlacement[];
        server_pages: string[];
        revision: string;
    };
}
/* eslint-enable camelcase */

export default async (serverUuid?: string): Promise<PanelAlert[]> => {
    const { data } = await http.get(serverUuid ? `/api/client/servers/${serverUuid}/alerts` : '/api/client/alerts');

    return (data.data as PanelAlertResponse[]).map(({ attributes }) => ({
        id: attributes.id,
        title: attributes.title,
        messageHtml: attributes.message_html,
        type: attributes.type,
        dismissible: attributes.dismissible,
        placements: attributes.placements,
        serverPages: attributes.server_pages,
        revision: attributes.revision,
    }));
};
