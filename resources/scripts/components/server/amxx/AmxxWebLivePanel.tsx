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
import getCvars from '@/api/server/amxx/getCvars';
import queryCvar from '@/api/server/amxx/queryCvar';
import setCvar from '@/api/server/amxx/setCvar';
import { AmxxCvar } from '@/api/server/amxx/types';

const ACTIVITY_PER_PAGE = 5;

interface Props {
    uuid: string;
    isServerRunning: boolean;
}

export default ({ uuid, isServerRunning }: Props) => {
    const { t } = useTranslation('strings');
    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const [cvars, setCvars] = useState<AmxxCvar[]>([]);
    const [cvarsLoading, setCvarsLoading] = useState(false);
    const [cvarDrafts, setCvarDrafts] = useState<Record<string, string>>({});
    const [cvarBusy, setCvarBusy] = useState<string | null>(null);
    const [activityPage, setActivityPage] = useState(1);

    const activityFilters = useMemo(
        () => ({
            page: activityPage,
            sorts: { timestamp: -1 as const },
            filters: { event: 'server:amxx' },
        }),
        [activityPage]
    );
    const { data: activityData, isValidating: activityLoading } = useActivityLogs(activityFilters, {
        perPage: ACTIVITY_PER_PAGE,
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    const loadCvars = async () => {
        if (!isServerRunning) {
            return;
        }

        setCvarsLoading(true);
        try {
            const definitions = await getCvars(uuid);
            setCvars(definitions);
            setCvarDrafts(
                definitions.reduce<Record<string, string>>((acc, cvar) => {
                    acc[cvar.name] = cvar.value ?? '';
                    return acc;
                }, {})
            );

            const queried = await Promise.all(
                definitions.map(async (definition) => {
                    try {
                        return await queryCvar(uuid, definition.name);
                    } catch {
                        return definition;
                    }
                })
            );

            setCvars(queried);
            setCvarDrafts(
                queried.reduce<Record<string, string>>((acc, cvar) => {
                    acc[cvar.name] = cvar.value ?? '';
                    return acc;
                }, {})
            );
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setCvarsLoading(false);
        }
    };

    useEffect(() => {
        if (isServerRunning) {
            loadCvars();
        }
    }, [uuid, isServerRunning]);

    const onSaveCvar = async (name: string) => {
        const value = cvarDrafts[name];
        if (value === undefined) {
            return;
        }

        clearFlashes('amxx:web');
        setCvarBusy(name);
        try {
            await setCvar(uuid, name, value);
            addFlash({
                key: 'amxx:web',
                type: 'success',
                message: t('server_amxx_web.cvars_success', { name, value }),
            });
            await loadCvars();
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:web', error: err });
        } finally {
            setCvarBusy(null);
        }
    };

    return (
        <div css={tw`space-y-4 mt-4`}>
            <Can action={['amxx.cvar', 'control.console']} matchAny>
                <TitledGreyBox
                    title={
                        <div css={tw`flex items-center justify-between w-full gap-3`}>
                            <span>{t('server_amxx_web.cvars_title')}</span>
                            {isServerRunning && (
                                <Button
                                    size={Button.Sizes.Small}
                                    shape={Button.Shapes.IconSquare}
                                    variant={Button.Variants.Secondary}
                                    disabled={cvarsLoading}
                                    title={t('server_amxx_web.cvars_refresh')}
                                    aria-label={t('server_amxx_web.cvars_refresh')}
                                    onClick={loadCvars}
                                >
                                    {cvarsLoading ? (
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
                        <p css={emptyStateText}>{t('server_amxx_web.offline')}</p>
                    ) : cvarsLoading && cvars.length === 0 ? (
                        <Spinner size={'small'} centered />
                    ) : (
                        <div css={tw`overflow-x-auto`}>
                            <table css={tw`w-full text-sm text-left text-neutral-200`}>
                                <thead>
                                    <tr css={tw`border-b border-neutral-700 text-neutral-400 uppercase text-xs`}>
                                        <th css={tw`py-2 pr-4`}>{t('server_amxx_web.cvars_name')}</th>
                                        <th css={tw`py-2 pr-4`}>{t('server_amxx_web.cvars_value')}</th>
                                        <th css={tw`py-2`} />
                                    </tr>
                                </thead>
                                <tbody>
                                    {cvars.map((cvar) => (
                                        <tr key={cvar.name} css={tw`border-b border-neutral-800`}>
                                            <td css={tw`py-2 pr-4`}>
                                                <div css={tw`font-mono text-xs`}>{cvar.name}</div>
                                                <div css={tw`text-xs text-neutral-500`}>{cvar.label}</div>
                                            </td>
                                            <td css={tw`py-2 pr-4`}>
                                                <Input
                                                    value={cvarDrafts[cvar.name] ?? ''}
                                                    onChange={(e) =>
                                                        setCvarDrafts((current) => ({
                                                            ...current,
                                                            [cvar.name]: e.target.value,
                                                        }))
                                                    }
                                                />
                                            </td>
                                            <td css={tw`py-2`}>
                                                <Button
                                                    size={Button.Sizes.Small}
                                                    disabled={cvarBusy === cvar.name}
                                                    onClick={() => onSaveCvar(cvar.name)}
                                                >
                                                    {cvarBusy === cvar.name
                                                        ? <Spinner size={'small'} />
                                                        : t('server_amxx_web.cvars_save')}
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

            <Can action={['amxx.read', 'activity.read', 'control.console']} matchAny>
                <TitledGreyBox title={t('server_amxx_web.activity_title')}>
                    {activityLoading && !activityData ? (
                        <Spinner size={'small'} centered />
                    ) : !activityData?.items.length ? (
                        <p css={emptyStateText}>{t('server_amxx_web.activity_empty')}</p>
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
