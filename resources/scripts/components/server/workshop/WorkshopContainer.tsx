import React, { useEffect, useMemo, useState } from 'react';
import tw from 'twin.macro';
import { css } from 'styled-components/macro';
import { ServerContext } from '@/state/server';
import getWorkshopBrowse from '@/api/swr/getWorkshopBrowse';
import getWorkshopInstalled from '@/api/swr/getWorkshopInstalled';
import installWorkshopItem from '@/api/server/installWorkshopItem';
import uninstallWorkshopItem from '@/api/server/uninstallWorkshopItem';
import { WorkshopItem } from '@/api/server/workshop/types';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import Select from '@/components/elements/Select';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import useFlash from '@/plugins/useFlash';
import { httpErrorToHuman } from '@/api/http';
import { emptyStateText } from '@/assets/css/cardTheme';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faExternalLinkAlt, faPlus, faSearch, faTrash } from '@fortawesome/free-solid-svg-icons';
import { useTranslation } from 'react-i18next';

const formatBytes = (bytes: number): string => {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }
    return `${value.toFixed(value >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
};

const WorkshopCard = ({
    item,
    installed,
    onInstall,
    onUninstall,
    onDetails,
    installing,
}: {
    item: WorkshopItem;
    installed: boolean;
    onInstall: (item: WorkshopItem) => void;
    onUninstall: (item: WorkshopItem) => void;
    onDetails: (item: WorkshopItem) => void;
    installing: boolean;
}) => {
    const { t } = useTranslation('strings');

    return (
        <div css={tw`bg-neutral-700 rounded border border-neutral-600 overflow-hidden flex flex-col`}>
            <div css={tw`h-40 bg-neutral-800 relative overflow-hidden`}>
                {item.previewUrl ? (
                    <img src={item.previewUrl} alt={item.title} css={tw`w-full h-full object-cover`} loading={'lazy'} />
                ) : (
                    <div css={tw`w-full h-full flex items-center justify-center text-neutral-500 text-xs`}>
                        {t('server_workshop.no_preview')}
                    </div>
                )}
                {item.isCollection && (
                    <span css={tw`absolute top-2 left-2 bg-blue-600 text-white text-xs px-2 py-1 rounded`}>
                        {t('server_workshop.collection')}
                    </span>
                )}
            </div>
            <div css={tw`p-3 flex-1 flex flex-col gap-2`}>
                <p css={tw`text-sm font-medium text-neutral-100 truncate`}>{item.title}</p>
                <p css={tw`text-xs text-neutral-400`}>
                    {t('server_workshop.votes', { count: item.votesUp.toLocaleString() })}
                    {' · '}
                    {formatBytes(item.fileSize)}
                </p>
                <div css={tw`mt-auto flex gap-2`}>
                    <Button.Text size={Button.Sizes.Small} onClick={() => onDetails(item)}>
                        {t('server_workshop.details')}
                    </Button.Text>
                    {installed ? (
                        <Button.Danger size={Button.Sizes.Small} onClick={() => onUninstall(item)} disabled={installing}>
                            <FontAwesomeIcon icon={faTrash} css={tw`mr-1`} />
                            {t('server_workshop.remove')}
                        </Button.Danger>
                    ) : (
                        <Button size={Button.Sizes.Small} onClick={() => onInstall(item)} disabled={installing}>
                            <FontAwesomeIcon icon={faPlus} css={tw`mr-1`} />
                            {t('server_workshop.add')}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
};

export default () => {
    const { t } = useTranslation('strings');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures);
    const { clearFlashes, addFlash, clearAndAddHttpError } = useFlash();

    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState<'trending' | 'popular' | 'recent'>('trending');
    const [cursor, setCursor] = useState<string | null>('*');
    const [cursorStack, setCursorStack] = useState<string[]>([]);
    const [installingId, setInstallingId] = useState<string | null>(null);
    const [detailItem, setDetailItem] = useState<WorkshopItem | null>(null);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setSearch(searchInput.trim());
            setCursor('*');
            setCursorStack([]);
        }, 350);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    const { data: browseData, error: browseError, isValidating: browseLoading } = getWorkshopBrowse(uuid, {
        q: search,
        sort,
        cursor,
    });
    const { data: installedData, mutate: mutateInstalled, error: installedError } = getWorkshopInstalled(uuid);
    const installedItems = installedData?.items;
    const syncInfo = installedData?.sync ?? browseData?.sync ?? null;

    const installedIds = useMemo(
        () => new Set((installedItems ?? []).map((item) => item.publishedFileId)),
        [installedItems]
    );

    if (!eggFeatures.includes('workshop')) {
        return (
            <ServerContentBlock title={t('server_workshop.title')}>
                <p css={tw`text-neutral-300`}>{t('server_workshop.unavailable')}</p>
            </ServerContentBlock>
        );
    }

    if (browseError && !browseData) {
        return <ServerError message={httpErrorToHuman(browseError)} onRetry={() => window.location.reload()} />;
    }

    const handleInstall = (item: WorkshopItem) => {
        setInstallingId(item.publishedFileId);
        clearFlashes('server:workshop');
        installWorkshopItem(uuid, item.publishedFileId)
            .then(() => {
                mutateInstalled();
                const messageKey =
                    syncInfo?.id === 'l4d2_vpk' ? 'server_workshop.install_success_l4d2' : 'server_workshop.install_success';
                addFlash({
                    key: 'server:workshop',
                    type: 'success',
                    message: t(messageKey),
                });
            })
            .catch((error) => clearAndAddHttpError({ key: 'server:workshop', error }))
            .finally(() => setInstallingId(null));
    };

    const handleUninstall = (item: WorkshopItem) => {
        setInstallingId(item.publishedFileId);
        clearFlashes('server:workshop');
        uninstallWorkshopItem(uuid, item.publishedFileId)
            .then(() => {
                mutateInstalled();
                addFlash({
                    key: 'server:workshop',
                    type: 'success',
                    message: t('server_workshop.remove_success'),
                });
            })
            .catch((error) => clearAndAddHttpError({ key: 'server:workshop', error }))
            .finally(() => setInstallingId(null));
    };

    const goNext = () => {
        if (!browseData?.nextCursor) return;
        setCursorStack((stack) => [...stack, cursor || '*']);
        setCursor(browseData.nextCursor);
    };

    const goPrev = () => {
        setCursorStack((stack) => {
            const next = [...stack];
            const previous = next.pop();
            setCursor(previous || '*');
            return next;
        });
    };

    return (
        <ServerContentBlock title={t('server_workshop.title')}>
            <FlashMessageRender byKey={'server:workshop'} css={tw`mb-4`} />

            <TitledGreyBox title={t('server_workshop.installed_title')} css={tw`mb-6`}>
                {installedError && !installedItems ? (
                    <p css={tw`text-red-400 text-sm`}>{httpErrorToHuman(installedError)}</p>
                ) : !installedItems ? (
                    <Spinner size={'small'} centered />
                ) : installedItems.length === 0 ? (
                    <p css={emptyStateText}>{t('server_workshop.installed_empty')}</p>
                ) : (
                    <div css={tw`grid gap-3 md:grid-cols-2 xl:grid-cols-3`}>
                        {installedItems.map((item) => (
                            <div key={item.id} css={tw`flex items-center gap-3 bg-neutral-800 rounded p-3 border border-neutral-600`}>
                                {item.previewUrl ? (
                                    <img src={item.previewUrl} alt={item.title || item.publishedFileId} css={tw`w-16 h-10 object-cover rounded`} />
                                ) : (
                                    <div css={tw`w-16 h-10 bg-neutral-700 rounded`} />
                                )}
                                <div css={tw`flex-1 min-w-0`}>
                                    <p css={tw`text-sm text-neutral-100 truncate`}>{item.title || item.publishedFileId}</p>
                                    <p css={tw`text-xs text-neutral-500 font-mono`}>{item.publishedFileId}</p>
                                </div>
                                <Button.Danger
                                    size={Button.Sizes.Small}
                                    onClick={() =>
                                        handleUninstall({
                                            publishedFileId: item.publishedFileId,
                                            title: item.title || item.publishedFileId,
                                        } as WorkshopItem)
                                    }
                                    disabled={installingId === item.publishedFileId}
                                >
                                    <FontAwesomeIcon icon={faTrash} />
                                </Button.Danger>
                            </div>
                        ))}
                    </div>
                )}
            </TitledGreyBox>

            <TitledGreyBox title={t('server_workshop.browse_title')}>
                <div css={tw`grid gap-4 md:grid-cols-3 mb-4`}>
                    <div>
                        <Label htmlFor={'workshop-search'}>{t('server_workshop.search')}</Label>
                        <div css={tw`relative`}>
                            <FontAwesomeIcon
                                icon={faSearch}
                                css={css`
                                    position: absolute;
                                    left: 0.75rem;
                                    top: 50%;
                                    transform: translateY(-50%);
                                    color: var(--color-text-muted, #737373);
                                    pointer-events: none;
                                    font-size: 0.875rem;
                                `}
                            />
                            <Input
                                id={'workshop-search'}
                                value={searchInput}
                                onChange={(e) => setSearchInput(e.currentTarget.value)}
                                placeholder={t('server_workshop.search_placeholder')}
                                css={css`
                                    padding-left: 2.25rem !important;
                                `}
                            />
                        </div>
                    </div>
                    <div>
                        <Label htmlFor={'workshop-sort'}>{t('server_workshop.sort')}</Label>
                        <Select
                            id={'workshop-sort'}
                            value={sort}
                            onChange={(e) => {
                                setSort(e.currentTarget.value as typeof sort);
                                setCursor('*');
                                setCursorStack([]);
                            }}
                        >
                            <option value={'trending'}>{t('server_workshop.sort_trending')}</option>
                            <option value={'popular'}>{t('server_workshop.sort_popular')}</option>
                            <option value={'recent'}>{t('server_workshop.sort_recent')}</option>
                        </Select>
                    </div>
                    <div css={tw`flex items-end`}>
                        {browseData?.appId ? (
                            <p css={tw`text-xs text-neutral-500 pb-2`}>
                                {t('server_workshop.app_id', { id: browseData.appId })}
                            </p>
                        ) : null}
                    </div>
                </div>

                {!browseData && browseLoading ? (
                    <Spinner size={'large'} centered />
                ) : (
                    <>
                        <div css={tw`grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4`}>
                            {(browseData?.items ?? []).map((item) => (
                                <WorkshopCard
                                    key={item.publishedFileId}
                                    item={item}
                                    installed={installedIds.has(item.publishedFileId)}
                                    onInstall={handleInstall}
                                    onUninstall={handleUninstall}
                                    onDetails={setDetailItem}
                                    installing={installingId === item.publishedFileId}
                                />
                            ))}
                        </div>

                        {(browseData?.items?.length ?? 0) === 0 && (
                            <p css={[emptyStateText, tw`mt-4`]}>{t('server_workshop.no_results')}</p>
                        )}

                        <div css={tw`flex items-center justify-between mt-6`}>
                            <Button.Text disabled={cursorStack.length === 0 || browseLoading} onClick={goPrev}>
                                {t('server_workshop.prev_page')}
                            </Button.Text>
                            <p css={tw`text-xs text-neutral-500`}>
                                {t('server_workshop.results_count', { count: browseData?.total ?? 0 })}
                            </p>
                            <Button.Text disabled={!browseData?.nextCursor || browseLoading} onClick={goNext}>
                                {t('server_workshop.next_page')}
                            </Button.Text>
                        </div>
                    </>
                )}
            </TitledGreyBox>

            <Dialog open={!!detailItem} onClose={() => setDetailItem(null)} title={detailItem?.title || t('server_workshop.details')}>
                {detailItem && (
                    <div css={tw`space-y-4`}>
                        {detailItem.previewUrl && (
                            <img src={detailItem.previewUrl} alt={detailItem.title} css={tw`w-full rounded border border-neutral-600`} />
                        )}
                        <p css={tw`text-sm text-neutral-300 whitespace-pre-wrap`}>
                            {detailItem.description || t('server_workshop.no_description')}
                        </p>
                        {detailItem.tags.length > 0 && (
                            <div css={tw`flex flex-wrap gap-2`}>
                                {detailItem.tags.map((tag) => (
                                    <span key={tag} css={tw`text-xs bg-neutral-700 px-2 py-1 rounded text-neutral-300`}>
                                        {tag}
                                    </span>
                                ))}
                            </div>
                        )}
                        <div css={tw`flex gap-2`}>
                            {detailItem.workshopUrl && (
                                <a href={detailItem.workshopUrl} target={'_blank'} rel={'noopener noreferrer'}>
                                    <Button.Text>
                                        <FontAwesomeIcon icon={faExternalLinkAlt} css={tw`mr-2`} />
                                        Steam
                                    </Button.Text>
                                </a>
                            )}
                            {installedIds.has(detailItem.publishedFileId) ? (
                                <Button.Danger onClick={() => handleUninstall(detailItem)} disabled={!!installingId}>
                                    {t('server_workshop.remove')}
                                </Button.Danger>
                            ) : (
                                <Button onClick={() => handleInstall(detailItem)} disabled={!!installingId}>
                                    {t('server_workshop.add')}
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </Dialog>
        </ServerContentBlock>
    );
};
