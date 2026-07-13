import React, { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import Can from '@/components/elements/Can';
import { useActivityLogs } from '@/api/server/activity';
import ActivityLogEntry from '@/components/elements/activity/ActivityLogEntry';
import ActivityLogList from '@/components/elements/activity/ActivityLogList';
import PaginationFooter from '@/components/elements/table/PaginationFooter';

const ACTIVITY_PER_PAGE = 5;

export default () => {
    const { t } = useTranslation('strings');
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

    return (
        <div css={tw`space-y-4 mt-4`}>
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
