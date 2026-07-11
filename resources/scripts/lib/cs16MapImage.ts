/** Pré-visualizações de mapas CS 1.6 (Liquipedia Commons). */
const CS16_MAP_IMAGE_URLS: Record<string, string> = {
    de_dust2: 'https://liquipedia.net/commons/images/thumb/d/d3/Cs_dust2.jpg/509px-Cs_dust2.jpg',
    de_aztec: 'https://liquipedia.net/commons/images/thumb/1/17/Cs_aztec.jpg/573px-Cs_aztec.jpg',
    cs_clan1mill: 'https://liquipedia.net/commons/images/thumb/9/9d/Cs_clan1mill.jpg/534px-Cs_clan1mill.jpg',
    cpl_mill: 'https://liquipedia.net/commons/images/thumb/5/53/Cs_cplmill.jpg/592px-Cs_cplmill.jpg',
    de_dust: 'https://liquipedia.net/commons/images/thumb/e/eb/Cs_dust.jpg/479px-Cs_dust.jpg',
    de_nuke: 'https://liquipedia.net/commons/images/thumb/e/ef/Cs_nuke.jpg/479px-Cs_nuke.jpg',
    de_inferno: 'https://liquipedia.net/commons/images/thumb/e/e4/Cs_inferno.jpg/511px-Cs_inferno.jpg',
    de_mirage: 'https://liquipedia.net/commons/images/thumb/c/c8/Cs_mirage.jpg/509px-Cs_mirage.jpg',
    de_prodigy: 'https://liquipedia.net/commons/images/thumb/8/89/Cs_prodigy.jpg/534px-Cs_prodigy.jpg',
    de_train: 'https://liquipedia.net/commons/images/thumb/0/02/Cs_train.jpg/476px-Cs_train.jpg',
    de_tuscan: 'https://liquipedia.net/commons/images/thumb/7/74/Cs_tuscan.jpg/534px-Cs_tuscan.jpg',
};

const CS16_MAP_ALIASES: Record<string, string> = {
    dust2: 'de_dust2',
    aztec: 'de_aztec',
    clan1_mill: 'cs_clan1mill',
    clan1mill: 'cs_clan1mill',
    cs_cplmill: 'cpl_mill',
    cplmill: 'cpl_mill',
    dust: 'de_dust',
    nuke: 'de_nuke',
    cs_inferno: 'de_inferno',
    inferno: 'de_inferno',
    mirage: 'de_mirage',
    prodigy: 'de_prodigy',
    train: 'de_train',
    cs_tuscan: 'de_tuscan',
    tuscan: 'de_tuscan',
};

const normalizeMapName = (map: string): string =>
    map.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');

export const getCs16MapImageUrl = (map: string): string | null => {
    const normalized = normalizeMapName(map);
    if (!normalized) {
        return null;
    }

    if (CS16_MAP_IMAGE_URLS[normalized]) {
        return CS16_MAP_IMAGE_URLS[normalized];
    }

    const alias = CS16_MAP_ALIASES[normalized];
    if (alias && CS16_MAP_IMAGE_URLS[alias]) {
        return CS16_MAP_IMAGE_URLS[alias];
    }

    const withoutPrefix = normalized.replace(/^(de|cs|fy)_/, '');
    const canonical = CS16_MAP_ALIASES[withoutPrefix];
    if (canonical && CS16_MAP_IMAGE_URLS[canonical]) {
        return CS16_MAP_IMAGE_URLS[canonical];
    }

    return null;
};
