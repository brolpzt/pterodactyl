import http from '@/api/http';
import { Ts3HtmlViewer } from './types';

export default async (uuid: string): Promise<Ts3HtmlViewer> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/ts3/html-viewer`);
    return data.attributes;
};
