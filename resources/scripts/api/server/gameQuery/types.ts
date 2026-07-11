export interface GameQueryPlayer {
    name?: string;
    score?: number;
    time?: number;
    ping?: number;
}

export interface GameQueryResult {
    online: boolean;
    type: string;
    address: string;
    port: number;
    query_port?: number;
    hostname: string | null;
    map: string | null;
    game: string | null;
    players: number;
    max_players: number;
    password_protected: boolean;
    version: string | null;
    player_list: GameQueryPlayer[];
}
