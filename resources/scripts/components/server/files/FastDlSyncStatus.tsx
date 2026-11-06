import React from 'react';
import tw from 'twin.macro';
import { useTranslation } from 'react-i18next';
import { CloudUploadIcon, ExclamationCircleIcon, InformationCircleIcon } from '@heroicons/react/solid';
import { ServerContext } from '@/state/server';
import useFastDlStatus from '@/api/swr/getFastDlStatus';
import { bytesToString } from '@/lib/formatters';
import CopyOnClick from '@/components/elements/CopyOnClick';
import GreyRowBox from '@/components/elements/GreyRowBox';
import Tooltip from '@/components/elements/tooltip/Tooltip';

const formatDate = (value?: string) =>
    value ? new Date(value).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '-';

/**
 * One-line FastDL summary on the file manager: sync state (with progress while
 * it runs) and the public URL. Only rendered for servers whose game supports FastDL.
 */
export default () => {
    const { t } = useTranslation('strings');
    const fastdlEnabled = ServerContext.useStoreState((state) => state.server.data!.fastdlEnabled);
    const fastdlUrl = ServerContext.useStoreState((state) => state.server.data!.fastdlUrl);
    const { data, error } = useFastDlStatus(fastdlEnabled);

    if (!fastdlEnabled) {
        return null;
    }

    const sync = data?.sync ?? null;
    const auto = data?.auto_sync ?? false;
    let message: string;
    let running = false;
    let failure: string | null = null;
    let progress: number | null = null;

    if (!data && !error) {
        message = t('fastdl.loading');
    } else if (error || !sync) {
        message = t('fastdl.unavailable');
    } else if (sync.state === 'running') {
        running = true;
        if (sync.phase === 'uploading' && sync.files_total > 0) {
            progress =
                sync.bytes_total > 0
                    ? (sync.bytes_done / sync.bytes_total) * 100
                    : (sync.files_done / sync.files_total) * 100;
            progress = Math.min(100, Math.max(0, progress));
            message = t('fastdl.uploading', {
                done: sync.files_done,
                total: sync.files_total,
                bytes_done: bytesToString(sync.bytes_done),
                bytes_total: bytesToString(sync.bytes_total),
                percent: Math.floor(progress),
            });
        } else {
            message = t(sync.phase === 'cleaning' ? 'fastdl.cleaning' : 'fastdl.running');
        }
    } else if (sync.state === 'completed') {
        message = t(auto ? 'fastdl.completed_auto' : 'fastdl.completed', { date: formatDate(sync.finished_at) });
    } else if (sync.state === 'failed') {
        message = t('fastdl.failed', { date: formatDate(sync.finished_at) });
        failure = sync.error || null;
    } else {
        message = t(auto ? 'fastdl.never_auto' : 'fastdl.never');
    }

    const blocked = data?.manifest && !data.manifest.exists && sync?.state !== 'running';

    return (
        <GreyRowBox $compact $hoverable={false} css={tw`mb-4 flex-wrap gap-x-4 gap-y-2 py-2`}>
            <div className={'flex min-w-0 flex-1 items-center gap-2'}>
                <CloudUploadIcon className={'h-5 w-5 flex-shrink-0'} style={{ color: 'var(--color-primary)' }} />
                <span className={'font-semibold'}>FastDL</span>
                {running && (
                    <span
                        className={'h-2 w-2 flex-shrink-0 animate-pulse rounded-full'}
                        style={{ background: 'var(--color-primary)' }}
                    />
                )}
                <span
                    className={'truncate'}
                    style={{ color: running ? 'var(--color-white)' : 'var(--color-text-muted)' }}
                >
                    {message}
                </span>
                {auto && !failure && !blocked && (
                    <Tooltip content={t('fastdl.auto_hint')} placement={'top'}>
                        <InformationCircleIcon
                            className={'h-4 w-4 flex-shrink-0 cursor-help'}
                            style={{ color: 'var(--color-text-muted)' }}
                        />
                    </Tooltip>
                )}
                {(failure || blocked) && (
                    <Tooltip content={blocked ? t('fastdl.no_manifest') : failure!} placement={'top'}>
                        <ExclamationCircleIcon
                            className={'h-4 w-4 flex-shrink-0 cursor-help'}
                            style={{ color: 'var(--color-danger)' }}
                        />
                    </Tooltip>
                )}
            </div>
            {fastdlUrl && (
                <div className={'flex min-w-0 max-w-full items-center gap-2'}>
                    <span style={{ color: 'var(--color-text-muted)' }}>{t('fastdl.url')}</span>
                    <CopyOnClick text={fastdlUrl}>
                        <code
                            className={'truncate rounded px-2 py-0.5 font-mono text-xs'}
                            style={{ background: 'color-mix(in srgb, var(--color-divider) 45%, transparent)' }}
                            title={t('fastdl.copy_url')}
                        >
                            {fastdlUrl}
                        </code>
                    </CopyOnClick>
                </div>
            )}
            {progress !== null && (
                <div
                    data-file-row-control
                    className={'absolute bottom-0 left-0 z-[1] h-1 rounded-b'}
                    style={{
                        width: `${progress.toFixed(1)}%`,
                        background: 'var(--color-primary)',
                        transition: 'width 0.5s',
                    }}
                />
            )}
        </GreyRowBox>
    );
};
