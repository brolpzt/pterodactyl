import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faSyncAlt } from '@fortawesome/free-solid-svg-icons';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import Input from '@/components/elements/Input';
import { Button } from '@/components/elements/button/index';
import Can from '@/components/elements/Can';
import useFlash from '@/plugins/useFlash';
import { useActivityLogs } from '@/api/server/activity';
import ActivityLogEntry from '@/components/elements/activity/ActivityLogEntry';
import ActivityLogList from '@/components/elements/activity/ActivityLogList';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import getDvars from '@/api/server/webrcon/getDvars';
import setDvar from '@/api/server/webrcon/setDvar';
import { WebRconDvar } from '@/api/server/webrcon/types';

const ACTIVITY_PER_PAGE = 5;

interface Props {
    uuid: string;
    isServerRunning: boolean;
}

export default ({ uuid, isServerRunning }: Props) => {
    const { t } = useTranslation('strings');
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const [dvars, setDvars] = useState<WebRconDvar[]>([]);
    const [dvarsLoading, setDvarsLoading] = useState(false);
    const [dvarDrafts, setDvarDrafts] = useState<Record<string, string>>({});
    const [dvarBusy, setDvarBusy] = useState<string | null>(null);
    const [activityPage, setActivityPage] = useState(1);

    const activityFilters = useMemo(
        () => ({
            page: activityPage,
            sorts: { timestamp: -1 as const },
            filters: { event: 'server:webrcon' },
        }),
        [activityPage]
    );
    const { data: activityData, isValidating: activityLoading } = useActivityLogs(activityFilters, {
        perPage: ACTIVITY_PER_PAGE,
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    const loadDvars = async () => {
        if (!isServerRunning) {
            return;
        }

        setDvarsLoading(true);
        try {
            const definitions = await getDvars(uuid);
            setDvars(definitions);
            setDvarDrafts(
                definitions.reduce<Record<string, string>>((acc, dvar) => {
                    acc[dvar.name] = dvar.value ?? '';
                    return acc;
                }, {})
            );
        } catch (err) {
            clearAndAddHttpError({ key: 'webrcon:web', error: err });
        } finally {
            setDvarsLoading(false);
        }
    };

    useEffect(() => {
        if (isServerRunning) {
            loadDvars();
        }
    }, [uuid, isServerRunning]);

    const onSaveDvar = async (name: string) => {
        const value = dvarDrafts[name];
        if (value === undefined) {
            return;
        }

        clearFlashes('webrcon:web');
        setDvarBusy(name);
        try {
            await setDvar(uuid, name, value);
            addFlash({
                key: 'webrcon:web',
                type: 'success',
                message: t('server_webrcon.dvars_success', { name, value }),
            });
            await loadDvars();
        } catch (err) {
            clearAndAddHttpError({ key: 'webrcon:web', error: err });
        } finally {
            setDvarBusy(null);
        }
    };

    return (
        <div css={tw`space-y-4 mt-4`}>
            <Can action={['webrcon.dvar', 'control.console']} matchAny>
                <TitledGreyBox
                    title={
                        <div css={tw`flex items-center justify-between w-full gap-3`}>
                            <span>{t('server_webrcon.dvars_title')}</span>
                            {isServerRunning && (
                                <Button
                                    size={Button.Sizes.Small}
                                    shape={Button.Shapes.IconSquare}
                                    variant={Button.Variants.Secondary}
                                    disabled={dvarsLoading}
                                    title={t('server_webrcon.dvars_refresh')}
                                    aria-label={t('server_webrcon.dvars_refresh')}
                                    onClick={loadDvars}
                                >
                                    {dvarsLoading ? (
                                        <Spinner size={'small'} />
                                    ) : (
                                        <FontAwesomeIcon icon={faSyncAlt} css={tw`w-3 h-3`} />
                                    )}
                                </Button>
                            )}
                        </div>
                    }
                >
                    {!isServerRunning ? (
                        <p css={emptyStateText}>{t('server_webrcon.offline')}</p>
                    ) : dvarsLoading && dvars.length === 0 ? (
                        <Spinner size={'small'} centered />
                    ) : (
                        <div css={tw`overflow-x-auto`}>
                            <table css={tw`w-full text-sm text-left text-neutral-200`}>
                                <thead>
                                    <tr css={tw`border-b border-neutral-700 text-neutral-400 uppercase text-xs`}>
                                        <th css={tw`py-2 pr-4`}>{t('server_webrcon.dvars_name')}</th>
                                        <th css={tw`py-2 pr-4`}>{t('server_webrcon.dvars_value')}</th>
                                        <th css={tw`py-2`} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {dvars.map((dvar) => (
                                        <tr key={dvar.name} css={tw`border-b border-neutral-800`}>
                                            <td css={tw`py-2 pr-4`}>
                                                <div css={tw`font-mono text-xs`}>{dvar.name}</div>
                                                <div css={tw`text-xs text-neutral-500`}>{dvar.label}</div>
                                            </td>
                                            <td css={tw`py-2 pr-4`}>
                                                <Input
                                                    value={dvarDrafts[dvar.name] ?? ''}
                                                    onChange={(e) =>
                                                        setDvarDrafts((current) => ({
                                                            ...current,
                                                            [dvar.name]: e.target.value,
                                                        }))
                                                    }
                                                />
                                            </td>
                                            <td css={tw`py-2`}>
                                                <Button
                                                    size={Button.Sizes.Small}
                                                    disabled={dvarBusy === dvar.name}
                                                    onClick={() => onSaveDvar(dvar.name)}
                                                >
                                                    {dvarBusy === dvar.name
                                                        ? <Spinner size={'small'} />
                                                        : t('server_webrcon.dvars_save')}
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </TitledGreyBox>
            </Can>

            <Can action={['webrcon.read', 'activity.read', 'control.console']} matchAny>
                <TitledGreyBox title={t('server_webrcon.activity_title')}>
                    {activityLoading && !activityData ? (
                        <Spinner size={'small'} centered />
                    ) : !activityData?.items.length ? (
                        <p css={emptyStateText}>{t('server_webrcon.activity_empty')}</p>
                    ) : (
                        <>
                            <ActivityLogList>
                                {activityData.items.map((activity) => (
                                    <ActivityLogEntry key={activity.id} activity={activity}>
                                        <span />
                                    </ActivityLogEntry>
                                ))}
                            </ActivityLogList>
                            <PaginationFooter
                                pagination={activityData.pagination}
                                onPageSelect={setActivityPage}
                            />
                        </>
                    )}
                </TitledGreyBox>
            </Can>
        </div>
    );
};
