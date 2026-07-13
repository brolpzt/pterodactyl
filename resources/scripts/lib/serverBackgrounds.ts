/**
 * Imagens de fundo por jogo em public/bg/.
 * Configure o GameDig do egg com o slug da pasta (ex.: cod4, bo1) ou um alias abaixo.
 */
const GAME_BACKGROUNDS: Record<string, string[]> = {
    bo1: ['/bg/bo1/array1.webp', '/bg/bo1/cracked2.webp', '/bg/bo1/havana1.webp', '/bg/bo1/villa7.webp'],
    bo2: ['/bg/bo2/express3.webp', '/bg/bo2/plaza2.webp', '/bg/bo2/raid1.webp', '/bg/bo2/standoff1.webp'],
    cod1: [
        '/bg/cod1/brecourt4.webp',
        '/bg/cod1/dawnville3.webp',
        '/bg/cod1/depot3.webp',
        '/bg/cod1/powcamp2.webp',
        '/bg/cod1/rhinevalley1.webp',
        '/bg/cod1/rhinevalleyvehicles.webp',
    ],
    cod2: [
        '/bg/cod2/beltot1.webp',
        '/bg/cod2/burgundy1.webp',
        '/bg/cod2/caen6.webp',
        '/bg/cod2/stalingrad1-2.webp',
        '/bg/cod2/stmereeglise5.webp',
        '/bg/cod2/toujane7.webp',
    ],
    cod4: ['/bg/cod4/backlot1.webp', '/bg/cod4/broadcast1.webp', '/bg/cod4/crash2.webp', '/bg/cod4/wetwork3.webp'],
    coduo: ['/bg/coduo/cassino1.webp', '/bg/coduo/foy1.webp', '/bg/coduo/stanjel9.webp'],
    cs16: [
        '/bg/counterstrike16/113464063-6926e680-9400-11eb-977a-12d774fb89ca.jpg',
        '/bg/counterstrike16/cs-74-fy_pool_day.jpg',
        '/bg/counterstrike16/nuke.jpeg',
    ],
    cssource: [
        '/bg/cssource/0000000027.jpg',
        '/bg/cssource/0000000028.jpg',
        '/bg/cssource/0000000030.jpg',
        '/bg/cssource/0000000031.jpg',
    ],
    cz: ['/bg/cz/64e49a098273c.jpg'],
    gtasa: ['/bg/gtasa/thumb-1920-1077297.png'],
    gmod: [
        '/bg/gmod/ss_ff27d52a103d1685e4981673c4f700b860cb23de.jpg',
        '/bg/gmod/ss_d314d7dba0987b3e7f49154964bda0ae74ee5161.jpg',
        '/bg/gmod/ss_12741a4344b5cfee33726ae219476c8634517f7d.jpg',
        '/bg/gmod/ss_74a181b4584a04c88ee316df31a850ab2810fb79.jpg',
        '/bg/gmod/0000000826.jpg',
    ],
    iw4x: [
        '/bg/iw4x/ss_b93815c9b99f50c472def2c8934bc91210e83198.jpg',
        '/bg/iw4x/ss_e0efd331a5acdeaea910faba84cc94f5897ae7e4.jpg',
        '/bg/iw4x/ss_570db4c7592dc959883b9b3ae4ea80991d7cbcd4.jpg',
        '/bg/iw4x/ss_9a8893322d2e4718bb3ad762fa2d9deb07a012a6.jpg',
        '/bg/iw4x/ss_51a130e076354ed7aa025fda1ef9ba62c3ed767a.jpg',
    ],
    l4d2: [
        '/bg/l4d2/ss_2eae29fbdfe8e5e8999b96d8bb28c5db70507968.jpg',
        '/bg/l4d2/ss_73ad69168a263ce585e4823d9607d901719ca3c5.jpg',
        '/bg/l4d2/ss_1d9d6e638c8ad4b04fac0f49ca32947b31396f24.jpg',
        '/bg/l4d2/ss_2b06e1786598ab033411c27600de1868f023c663.jpg',
    ],
    mohaa: ['/bg/mohaa/Mohaa_omaha0001.webp', '/bg/mohaa/Mohaa_omaha0002.webp', '/bg/mohaa/Mohaa_snowypark0002.webp'],
    mohsh: ['/bg/mohsh/Ardennes_mp.webp', '/bg/mohsh/Stadt_mp.webp'],
    mw3: ['/bg/mw3/dome1-1.webp', '/bg/mw3/lockdown2.webp', '/bg/mw3/village5.webp'],
};

/** Aliases comuns de GameDig → slug da pasta em public/bg */
const GAMEDIG_ALIASES: Record<string, string> = {
    callofduty: 'cod1',
    callofdutyuo: 'coduo',
    callofduty2: 'cod2',
    callofduty4: 'cod4',
    cod: 'cod1',
    coduo: 'coduo',
    blackops: 'bo1',
    blackops2: 'bo2',
    t5: 'bo1',
    t6: 'bo2',
    modernwarfare3: 'mw3',
    modernwarfare2: 'iw4x',
    codmw2: 'iw4x',
    iw4x: 'iw4x',
    mw2: 'iw4x',
    counterstrike16: 'cs16',
    goldsource: 'cs16',
    cstrike: 'cs16',
    css: 'cssource',
    counterstrikesource: 'cssource',
    conditionzero: 'cz',
    medalofhonoralliedassault: 'mohaa',
    mohaa: 'mohaa',
    mohspearhead: 'mohsh',
    mohsh: 'mohsh',
    gtasa: 'gtasa',
    samp: 'gtasa',
    gmod: 'gmod',
    garrysmod: 'gmod',
    l4d2: 'l4d2',
    left4dead2: 'l4d2',
};

const EGG_NAME_PATTERNS: [RegExp, string][] = [
    [/black\s*ops\s*2|\bbo2\b/i, 'bo2'],
    [/black\s*ops|\bbo1\b/i, 'bo1'],
    [/modern\s*warfare\s*3|\bmw3\b/i, 'mw3'],
    [/modern\s*warfare\s*2|\bmw2\b|\biw4x\b/i, 'iw4x'],
    [/united\s*offensive|\bcoduo\b/i, 'coduo'],
    [/call\s*of\s*duty\s*4|\bcod\s*4\b/i, 'cod4'],
    [/call\s*of\s*duty\s*2|\bcod\s*2\b/i, 'cod2'],
    [/call\s*of\s*duty\s*1|\bcod\s*1\b/i, 'cod1'],
    [/condition\s*zero|\bcz\b/i, 'cz'],
    [/counter-?strike\s*1\.?6|\bcs\s*1\.?6\b|\bcs16\b/i, 'cs16'],
    [/counter-?strike\s*:?\s*source|\bcssource\b/i, 'cssource'],
    [/gta\s*san\s*andreas|\bsa-?mp\b|\bgtasa\b/i, 'gtasa'],
    [/garry\s*'?s?\s*mod|\bgmod\b|\bgarrysmod\b/i, 'gmod'],
    [/left\s*4\s*dead\s*2|\bl4d\s*2\b|\bl4d2\b/i, 'l4d2'],
    [/spearhead|\bmohsh\b/i, 'mohsh'],
    [/medal\s*of\s*honor|\bmohaa\b/i, 'mohaa'],
];

const GAME_SLUGS = Object.keys(GAME_BACKGROUNDS).sort((a, b) => b.length - a.length);

const normalizeSlug = (value: string): string => value.trim().toLowerCase().replace(/[^a-z0-9]/g, '');

const resolveFromNormalized = (normalized: string): string | null => {
    if (GAME_BACKGROUNDS[normalized]) {
        return normalized;
    }

    if (GAMEDIG_ALIASES[normalized]) {
        return GAMEDIG_ALIASES[normalized];
    }

    const aliasMatch = Object.entries(GAMEDIG_ALIASES).find(
        ([alias]) => normalized.includes(alias) || alias.includes(normalized)
    );
    if (aliasMatch) {
        return aliasMatch[1];
    }

    return GAME_SLUGS.find((slug) => normalized.includes(slug) || slug.includes(normalized)) ?? null;
};

export const resolveGameSlug = (gamedig?: string | null, eggName?: string | null): string | null => {
    if (gamedig) {
        const normalized = normalizeSlug(gamedig);
        const fromGamedig = normalized ? resolveFromNormalized(normalized) : null;
        if (fromGamedig) {
            return fromGamedig;
        }
    }

    if (!eggName) {
        return null;
    }

    for (const [pattern, slug] of EGG_NAME_PATTERNS) {
        if (pattern.test(eggName)) {
            return slug;
        }
    }

    const normalizedName = normalizeSlug(eggName);
    return normalizedName ? resolveFromNormalized(normalizedName) : null;
};

export const getServerBackgroundUrl = (gamedig?: string | null, eggName?: string | null): string | null => {
    const gameSlug = resolveGameSlug(gamedig, eggName);
    if (!gameSlug) {
        return null;
    }

    const backgrounds = GAME_BACKGROUNDS[gameSlug];
    if (!backgrounds?.length) {
        return null;
    }

    const index = Math.floor(Math.random() * backgrounds.length);
    return backgrounds[index];
};
