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
    channels: Ts3HtmlViewerChannel[];
    clients: Ts3HtmlViewerClient[];
}

export interface Ts3HtmlViewerChannel {
    cid?: string;
    pid?: string;
    channel_name?: string;
    total_clients?: string;
    channel_flag_password?: string;
    channel_needed_talk_power?: string;
    [key: string]: string | undefined;
}

export interface Ts3HtmlViewerClient {
    clid?: string;
    cid?: string;
    client_type?: string;
    client_nickname?: string;
    client_unique_identifier?: string;
    client_away?: string;
    [key: string]: string | undefined;
}
