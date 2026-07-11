export interface AmxxOverview {
    game_directory: string;
    users_ini_exists: boolean;
    banned_cfg_exists: boolean;
    listip_cfg_exists: boolean;
    paths: {
        game_dir: string;
        users_ini: string;
        banned_cfg: string;
        listip_cfg: string;
    };
    presets: string[];
    access_flags: Record<string, string>;
}

export interface AmxxAdmin {
    id: number;
    auth: string;
    password: string;
    access_flags: string;
    account_flags: string;
    auth_type: 'steamid' | 'ip' | 'nickname';
    nickname: string | null;
    enabled: boolean;
    comment: string | null;
    line: string;
}

export interface AmxxBan {
    id: string;
    type: 'steamid' | 'ip';
    identifier: string;
    minutes: number;
    reason: string | null;
    permanent: boolean;
}

export type AmxxAuthType = 'steamid' | 'ip' | 'nickname';
export type AmxxPreset = 'owner' | 'admin' | 'mod' | 'custom';

export const AMXX_ACCESS_FLAGS: Record<string, string> = {
    a: 'Imunidade',
    b: 'Reserva de slot',
    c: 'Kick (amx_kick)',
    d: 'Ban (amx_ban)',
    e: 'Slay/Slap',
    f: 'Trocar mapa (amx_map)',
    g: 'Cvars (amx_cvar)',
    h: 'Config (amx_cfg)',
    i: 'Chat admin',
    j: 'Votações',
    k: 'sv_password',
    l: 'RCON (amx_rcon)',
    m: 'Nível custom A',
    n: 'Nível custom B',
    o: 'Nível custom C',
    p: 'Nível custom D',
    q: 'Nível custom E',
    r: 'Nível custom F',
    s: 'Nível custom G',
    t: 'Nível custom H',
    u: 'Menu AMXX',
};

export const AMXX_PRESET_FLAGS: Record<Exclude<AmxxPreset, 'custom'>, string> = {
    owner: 'abcdefghijklmnopqrstu',
    admin: 'bcdefijklmnopqrstu',
    mod: 'bcdj',
};
