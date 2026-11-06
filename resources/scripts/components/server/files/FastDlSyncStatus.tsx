import React from 'react';
import { useTranslation } from 'react-i18next';
import { ServerContext } from '@/state/server';
import useFastDlStatus from '@/api/swr/getFastDlStatus';
import { bytesToString } from '@/lib/formatters';

const formatDate = (value?: string) => (value ? new Date(value).toLocaleString() : '-');

/**
 * FastDL progress for the user: running sync with a progress bar, or the
 * outcome of the last one.
 */
export default () => {
    const { t } = useTranslation('strings');
    const fastdlEnabled = ServerContext.useStoreState((state) => state.server.data!.fastdlEnabled);
    const { data, error } = useFastDlStatus(fastdlEnabled);

    if (!fastdlEnabled || (!data && !error)) {
        return null;
    }

    const sync = data?.sync ?? null;
    let message: string;
    let tone = 'text-neutral-300';
    let progress: number | null = null;

    if (error || !sync) {
        message = t('fastdl.unavailable');
        tone = 'text-neutral-400';
    } else if (sync.state === 'running') {
        if (sync.phase === 'uploading' && sync.files_total > 0) {
            message = t('fastdl.uploading', {
                done: sync.files_done,
                total: sync.files_total,
                bytes_done: bytesToString(sync.bytes_done),
                bytes_total: bytesToString(sync.bytes_total),
            });
            progress =
                sync.bytes_total > 0
                    ? (sync.bytes_done / sync.bytes_total) * 100
                    : (sync.files_done / sync.files_total) * 100;
        } else {
            message = t(sync.phase ? `fastdl.${sync.phase}` : 'fastdl.running');
        }
        tone = 'text-cyan-300';
    } else if (sync.state === 'completed') {
        message = t('fastdl.completed', {
            date: formatDate(sync.finished_at),
            uploaded: Math.max(0, sync.files_done - sync.files_failed),
            deleted: sync.deleted_files,
            skipped: sync.skipped_original_files,
        });
        tone = 'text-green-300';
    } else if (sync.state === 'failed') {
        message = t('fastdl.failed', { date: formatDate(sync.finished_at), error: sync.error || '-' });
        tone = 'text-red-300';
    } else {
        message = t('fastdl.never');
        tone = 'text-neutral-400';
    }

    const blocked = data?.manifest && !data.manifest.exists && sync?.state !== 'running';

    return (
        <div className={'mb-4 rounded bg-neutral-700/60 px-4 py-3 text-sm'}>
            <p className={tone}>{message}</p>
            {progress !== null && (
                <div className={'mt-2 h-1.5 w-full overflow-hidden rounded bg-neutral-900'}>
                    <div
                        className={'h-full bg-cyan-400 transition-all duration-500'}
                        style={{ width: `${Math.min(100, Math.max(0, progress)).toFixed(1)}%` }}
                    />
                </div>
            )}
            {blocked && <p className={'mt-1 text-yellow-300'}>{t('fastdl.no_manifest')}</p>}
        </div>
    );
};
