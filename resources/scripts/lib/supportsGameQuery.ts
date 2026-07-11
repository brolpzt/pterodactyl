/** Servidores TS3 usam API dedicada; demais jogos consultam via Wings quando gamedig está definido. */
export const supportsGameQuery = (
    gamedig?: string | null,
    eggId?: number | null
): boolean => {
    if (eggId === 12) {
        return false;
    }

    return Boolean(gamedig?.trim());
};
