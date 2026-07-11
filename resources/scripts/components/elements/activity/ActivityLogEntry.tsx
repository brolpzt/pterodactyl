import React from 'react';
import { Link } from 'react-router-dom';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Translate from '@/components/elements/Translate';
import { format, formatDistanceToNowStrict } from 'date-fns';
import { ActivityLog } from '@definitions/user';
import ActivityLogMetaButton from '@/components/elements/activity/ActivityLogMetaButton';
import { FolderOpenIcon, TerminalIcon } from '@heroicons/react/solid';
import classNames from 'classnames';
import style from './style.module.css';
import Avatar from '@/components/Avatar';
import useLocationHash from '@/plugins/useLocationHash';
import { getObjectKeys, isObject } from '@/lib/objects';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { cardLabelText, cardValueText } from '@/assets/css/cardTheme';

interface Props {
    activity: ActivityLog;
    children?: React.ReactNode;
}

const EntryRow = styled.div.attrs({ className: 'group' })`
    ${tw`grid grid-cols-10 py-4`};
    border-bottom: 1px solid rgba(45, 45, 58, 0.45);

    &:last-child {
        border-bottom: 0;
    }
`;

const AvatarWrap = styled.div`
    ${tw`flex items-center w-10 h-10 rounded-full overflow-hidden`};
    background: color-mix(in srgb, var(--color-divider) 38%, transparent);
`;

const EntryTitle = styled.div`
    ${tw`flex items-center`};
    ${cardValueText};
`;

const EntryMeta = styled.div`
    ${tw`mt-1 flex items-center text-sm`};
    ${cardLabelText};
`;

const EventLink = styled(Link)`
    ${tw`transition-colors duration-150`};
    color: inherit;

    &:hover,
    &:active {
        ${tw`text-primary-400`};
    }
`;

function wrapProperties(value: unknown): any {
    if (value === null || typeof value === 'string' || typeof value === 'number') {
        return `<strong>${String(value)}</strong>`;
    }

    if (isObject(value)) {
        return getObjectKeys(value).reduce((obj, key) => {
            if (key === 'count' || (typeof key === 'string' && key.endsWith('_count'))) {
                return { ...obj, [key]: value[key] };
            }
            return { ...obj, [key]: wrapProperties(value[key]) };
        }, {} as Record<string, unknown>);
    }

    if (Array.isArray(value)) {
        return value.map(wrapProperties);
    }

    return value;
}

export default ({ activity, children }: Props) => {
    const { pathTo } = useLocationHash();
    const actor = activity.relationships.actor;
    const properties = wrapProperties(activity.properties);

    return (
        <EntryRow>
            <div className={'hidden sm:flex sm:col-span-1 items-center justify-center select-none'}>
                <AvatarWrap>
                    <Avatar name={actor?.uuid || 'system'} />
                </AvatarWrap>
            </div>
            <div className={'col-span-10 sm:col-span-9 flex'}>
                <div className={'flex-1 px-4 sm:px-0'}>
                    <EntryTitle>
                        <Tooltip placement={'top'} content={actor?.email || 'System User'}>
                            <span>{actor?.username || 'System'}</span>
                        </Tooltip>
                        <span css={[cardLabelText, tw`mx-1`]}>&mdash;</span>
                        <EventLink to={`#${pathTo({ event: activity.event })}`}>
                            {activity.event}
                        </EventLink>
                        <div className={classNames(style.icons, 'group-hover:opacity-90')}>
                            {activity.isApi && (
                                <Tooltip placement={'top'} content={'Using API Key'}>
                                    <TerminalIcon />
                                </Tooltip>
                            )}
                            {activity.event.startsWith('server:sftp.') && (
                                <Tooltip placement={'top'} content={'Using SFTP'}>
                                    <FolderOpenIcon />
                                </Tooltip>
                            )}
                            {children}
                        </div>
                    </EntryTitle>
                    <p className={style.description}>
                        <Translate ns={'activity'} values={properties} i18nKey={activity.event.replace(':', '.')} />
                    </p>
                    <EntryMeta>
                        {activity.ip && (
                            <span>
                                {activity.ip}
                                <span css={[cardLabelText, tw`mx-1`]}>|</span>
                            </span>
                        )}
                        <Tooltip placement={'right'} content={format(activity.timestamp, 'MMM do, yyyy H:mm:ss')}>
                            <span>{formatDistanceToNowStrict(activity.timestamp, { addSuffix: true })}</span>
                        </Tooltip>
                    </EntryMeta>
                </div>
                {activity.hasAdditionalMetadata && <ActivityLogMetaButton meta={activity.properties} />}
            </div>
        </EntryRow>
    );
};
