import { stripGameHostnameColors } from '@/lib/stripGameHostnameColors';

describe('@/lib/stripGameHostnameColors.ts', function () {
    describe('stripGameHostnameColors()', function () {
        it('should strip GoldSrc caret color codes', function () {
            expect(stripGameHostnameColors('^1HostGamer ^2CS 1.6')).toBe('HostGamer CS 1.6');
            expect(stripGameHostnameColors('^0[^3BR^1] ^4Servidor')).toBe('[[BR] Servidor');
        });

        it('should preserve literal carets', function () {
            expect(stripGameHostnameColors('Servidor ^^VIP')).toBe('Servidor ^VIP');
        });

        it('should strip embedded HL color bytes', function () {
            const withBytes = `\x01Red\x02Green\x08Default`;
            expect(stripGameHostnameColors(withBytes)).toBe('RedGreenDefault');
        });

        it('should strip ampersand color codes', function () {
            expect(stripGameHostnameColors('&1Host &2Online')).toBe('Host Online');
        });

        it('should handle empty and nullish values', function () {
            expect(stripGameHostnameColors('')).toBe('');
            expect(stripGameHostnameColors(null)).toBe('');
            expect(stripGameHostnameColors(undefined)).toBe('');
        });
    });
});
