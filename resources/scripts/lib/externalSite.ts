import { SiteSettings } from '@/state/settings';

export const DEFAULT_EXTERNAL_SITE_URL = 'https://hostgamer.net';

export function getExternalSiteUrl(settings?: SiteSettings): string {
    return settings?.externalSiteUrl || DEFAULT_EXTERNAL_SITE_URL;
}

export function redirectToExternalSite(settings?: SiteSettings): void {
    window.location.href = getExternalSiteUrl(settings);
}
