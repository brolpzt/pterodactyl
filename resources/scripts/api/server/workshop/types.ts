export interface WorkshopItem {
    publishedFileId: string;
    title: string;
    description: string;
    previewUrl: string | null;
    fileSize: number;
    votesUp: number;
    timeUpdated: number | null;
    creator: string;
    tags: string[];
    isCollection: boolean;
    children: string[];
    workshopUrl: string | null;
}

export interface WorkshopBrowseResult {
    items: WorkshopItem[];
    total: number;
    nextCursor: string | null;
    appId: number;
}

export interface WorkshopInstalledItem {
    id: number;
    publishedFileId: string;
    title: string | null;
    previewUrl: string | null;
    sortOrder: number;
    createdAt: string | null;
}

export const mapWorkshopItem = (raw: Record<string, unknown>): WorkshopItem => ({
    publishedFileId: String(raw.published_file_id ?? ''),
    title: String(raw.title ?? ''),
    description: String(raw.description ?? ''),
    previewUrl: raw.preview_url ? String(raw.preview_url) : null,
    fileSize: Number(raw.file_size ?? 0),
    votesUp: Number(raw.votes_up ?? 0),
    timeUpdated: raw.time_updated != null ? Number(raw.time_updated) : null,
    creator: String(raw.creator ?? ''),
    tags: Array.isArray(raw.tags) ? raw.tags.map(String) : [],
    isCollection: Boolean(raw.is_collection),
    children: Array.isArray(raw.children) ? raw.children.map(String) : [],
    workshopUrl: raw.workshop_url ? String(raw.workshop_url) : null,
});

export const mapInstalledItem = (raw: Record<string, unknown>): WorkshopInstalledItem => ({
    id: Number(raw.id),
    publishedFileId: String(raw.published_file_id ?? ''),
    title: raw.title != null ? String(raw.title) : null,
    previewUrl: raw.preview_url != null ? String(raw.preview_url) : null,
    sortOrder: Number(raw.sort_order ?? 0),
    createdAt: raw.created_at != null ? String(raw.created_at) : null,
});
