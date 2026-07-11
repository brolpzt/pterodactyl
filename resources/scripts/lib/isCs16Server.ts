export const isCs16Server = (gamedig?: string | null, eggName?: string | null): boolean => {
    const normalizedGamedig = (gamedig || '').trim().toLowerCase();
    if (['cs16', 'counterstrike16', 'goldsource', 'cstrike'].includes(normalizedGamedig)) {
        return true;
    }

    const name = (eggName || '').trim();
    return /counter-?strike\s*1\.?6|\bcs\s*1\.?6\b|\bcs16\b|rehlds|\bhlds\b/i.test(name);
};
