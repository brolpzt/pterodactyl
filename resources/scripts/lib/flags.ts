/** Bandeiras SVG — mesmo padrão hostgamer.net (/images/flags/*.svg) */
export const FLAG_SLUGS = ['us', 'br', 'es', 'globe'] as const;

export type FlagSlug = (typeof FLAG_SLUGS)[number];

const CODE_TO_SLUG: Record<string, FlagSlug> = {
    US: 'us',
    EN: 'us',
    BR: 'br',
    PT: 'br',
    ES: 'es',
    AR: 'es',
};

export const resolveFlagSlug = (code?: string | null): FlagSlug => {
    if (!code) return 'globe';

    const normalized = code.trim().toUpperCase();
    const mapped = CODE_TO_SLUG[normalized] || CODE_TO_SLUG[normalized.substring(0, 2)];

    if (mapped) return mapped;

    const slug = normalized.substring(0, 2).toLowerCase();
    if ((FLAG_SLUGS as readonly string[]).includes(slug)) {
        return slug as FlagSlug;
    }

    return 'globe';
};

export const flagAssetUrl = (slug: FlagSlug) => `/images/flags/${slug}.svg`;
