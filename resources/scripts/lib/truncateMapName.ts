/** Soft cap for UI labels (status bar, toolbars). Full name stays in `title`. */
export const MAP_NAME_MAX_LENGTH = 8;

/** Keeps the first `max` characters and appends "…" when the name is longer. */
export const truncateMapName = (name: string, max: number = MAP_NAME_MAX_LENGTH): string => {
    const value = name.trim();
    if (value.length <= max) {
        return value;
    }

    return `${value.slice(0, Math.max(1, max))}…`;
};
