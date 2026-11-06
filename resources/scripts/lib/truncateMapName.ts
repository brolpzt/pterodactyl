export const MAP_NAME_MAX_LENGTH = 24;

export const truncateMapName = (name: string, max: number = MAP_NAME_MAX_LENGTH): string =>
    name.length > max ? `${name.slice(0, max - 1)}…` : name;
