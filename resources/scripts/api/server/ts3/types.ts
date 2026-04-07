export interface Ts3Overview {
    status: string;
    version: string | null;
    platform: string | null;
    build: string | null;
    uptime_seconds: number | null;
    clients_online: number | null;
    channels_online: number | null;
    query_ip: string | null;
    query_port: number | null;
    server_name: string | null;
    welcome_message: string | null;
}

export interface Ts3Ban {
    banid?: string;
    ip?: string;
    reason?: string;
    invokername?: string;
    duration?: string;
    created?: string;
    [key: string]: string | undefined;
}

export interface Ts3Token {
    token?: string;
    token_id1?: string;
    token_description?: string;
    [key: string]: string | undefined;
}

export interface Ts3Snapshot {
    uuid: string;
    name: string;
    created_by: number | null;
    created_at: string;
    updated_at: string;
}

export interface Ts3HtmlViewer {
    url: string;
    server_name: string | null;
    welcome_message: string | null;
}
