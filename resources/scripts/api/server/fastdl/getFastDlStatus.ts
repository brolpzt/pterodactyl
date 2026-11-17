import http from '@/api/http';

export interface FastDlSyncStatus {
    state: 'never' | 'running' | 'completed' | 'failed';
    phase?: 'scanning' | 'comparing' | 'uploading' | 'cleaning' | 'purging';
    storage_type?: string;
    started_at?: string;
    finished_at?: string;
    matched_files: number;
    skipped_original_files: number;
    files_total: number;
    files_done: number;
    files_failed: number;
    bytes_total: number;
    bytes_done: number;
    deleted_files: number;
    /** CDN URLs purged (http + https per overwritten or deleted file). */
    purged_urls?: number;
    purge_error?: string;
    error?: string;
}

export interface FastDlStatus {
    /** null when Wings could not be reached or does not report it. */
    sync: FastDlSyncStatus | null;
    manifest: { exists: boolean } | null;
    /** New or changed files are synchronized automatically. */
    auto_sync?: boolean;
}

export default async (uuid: string): Promise<FastDlStatus> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/fastdl`);

    return data.attributes as FastDlStatus;
};
