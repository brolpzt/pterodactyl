const CARET_PLACEHOLDER = '\uE000';

/**
 * Removes GoldSrc / Source color codes from a game server hostname so it can be
 * displayed as plain text (e.g. ^1, ^2, embedded HL color bytes).
 */
export function stripGameHostnameColors(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return value
        .replace(/\^\^/g, CARET_PLACEHOLDER)
        .replace(/\^[0-9a-zA-Z]/g, '')
        .replace(new RegExp(CARET_PLACEHOLDER, 'g'), '^')
        .replace(/&[0-9a-z]/gi, '')
        .replace(/[\u0001-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}
