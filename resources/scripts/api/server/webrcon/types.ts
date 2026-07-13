export interface WebRconOverview {
    game_directory: string;
    wings_reachable: boolean;
    hostname: string | null;
    map: string | null;
    max_clients: number | null;
    player_count: number;
    gamedig: string | null;
}

export interface WebRconPlayer {
    clientnum: number;
    name: string;
    score: number;
    ping: number;
    guid: string | null;
    address: string;
}

export interface WebRconPlayersResult {
    players: WebRconPlayer[];
    map?: string | null;
    hostname?: string | null;
    max_clients?: number | null;
}

export interface WebRconMap {
    name: string;
    file: string;
}
