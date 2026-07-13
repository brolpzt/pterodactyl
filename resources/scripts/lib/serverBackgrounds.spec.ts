import { getServerBackgroundUrl, resolveGameSlug } from '@/lib/serverBackgrounds';

describe('serverBackgrounds', () => {
    it('resolve l4d2 from gamedig slug', () => {
        expect(resolveGameSlug('l4d2', null)).toBe('l4d2');
        expect(resolveGameSlug('left-4-dead-2', null)).toBe('l4d2');
    });

    it('returns a background url for l4d2', () => {
        const url = getServerBackgroundUrl('l4d2', 'Left 4 Dead 2');
        expect(url).toMatch(/^\/bg\/l4d2\/.+\.jpg$/);
    });

    it('resolve gmod and cssource from gamedig', () => {
        expect(resolveGameSlug('gmod', null)).toBe('gmod');
        expect(resolveGameSlug('css', null)).toBe('cssource');
    });
});
