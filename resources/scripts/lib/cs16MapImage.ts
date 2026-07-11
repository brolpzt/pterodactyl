export const getCs16MapFallbackImageUrl = (map: string): string =>
    `https://image.gametracker.com/images/maps/256x192/cs/${encodeURIComponent(map.toLowerCase())}.jpg`;
