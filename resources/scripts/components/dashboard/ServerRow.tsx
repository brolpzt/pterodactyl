import React, { memo, useEffect, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEthernet, faGlobe, faHdd, faMemory, faMicrochip, faServer, IconDefinition } from '@fortawesome/free-solid-svg-icons';
import { Link } from 'react-router-dom';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import tw, { css } from 'twin.macro';
import GreyRowBox from '@/components/elements/GreyRowBox';
import Spinner from '@/components/elements/Spinner';
import styled from 'styled-components/macro';
import isEqual from 'react-fast-compare';
import { hostgamerColors } from '@/lib/hostgamerTheme';
import { navText } from '@/assets/css/cardTheme';

const isAlarmState = (current: number, limit: number): boolean => limit > 0 && current / (limit * 1024 * 1024) >= 0.9;

const statusColor = ($status: ServerPowerState | undefined) => {
    if (!$status || $status === 'offline') {
        return hostgamerColors.statusOffline;
    }

    if ($status === 'running') {
        return hostgamerColors.statusOnline;
    }

    return hostgamerColors.statusStarting;
};

const statusBarStyles = ($status: ServerPowerState | undefined) => {
    const color = statusColor($status);

    return css`
        background-color: ${color};
        box-shadow: ${hostgamerColors.statusGlow} ${color}d9;
    `;
};

const serverRowGrid = css`
    display: grid;
    align-items: center;
    column-gap: 1rem;
    row-gap: 0.5rem;
    grid-template-columns: minmax(0, 1fr);

    @media (min-width: 640px) {
        grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    }

    @media (min-width: 768px) {
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.95fr) minmax(0, 1.05fr);
    }

    @media (min-width: 1024px) {
        grid-template-columns:
            minmax(0, 1.15fr)
            minmax(0, 0.95fr)
            minmax(0, 1.05fr)
            6.25rem
            7.25rem
            7.25rem;
    }
`;

const StatusIndicatorBox = styled(GreyRowBox).attrs({ $hoverable: true })<{
    $status: ServerPowerState | undefined;
}>`
    ${tw`relative overflow-visible pr-4 py-2.5 px-4`};
    ${serverRowGrid};

    & .status-bar {
        ${tw`w-1.5 absolute right-0 z-20 rounded-full my-1`};
        top: 0.375rem;
        bottom: 0.375rem;
        opacity: 1;

        ${({ $status }) => statusBarStyles($status)};
    }
`;

const Cell = styled.div<{ $hideBelow?: 'sm' | 'md' }>`
    ${tw`flex items-center gap-1.5 min-w-0`};

    ${(props) => props.$hideBelow === 'sm' && tw`hidden sm:flex`};
    ${(props) => props.$hideBelow === 'md' && tw`hidden md:flex`};
`;

const metricText = (alarm: boolean) => css`
    ${navText};
    ${tw`text-xs whitespace-nowrap tabular-nums`};
    ${alarm ? tw`text-white` : tw`opacity-80`};
`;

const metricLimit = css`
    ${navText};
    ${tw`text-xs opacity-50 tabular-nums`};
`;

const Metric = memo(
    ({
        icon,
        value,
        limit,
        alarm,
    }: {
        icon: IconDefinition;
        value: string;
        limit: string;
        alarm: boolean;
    }) => (
        <Cell css={tw`hidden lg:flex`}>
            <FontAwesomeIcon
                icon={icon}
                css={[tw`text-xs flex-shrink-0`, alarm ? tw`text-red-400` : tw`text-neutral-500`]}
                fixedWidth
            />
            <span css={metricText(alarm)}>
                {value}
                <span css={metricLimit}> / {limit}</span>
            </span>
        </Cell>
    ),
    isEqual
);

type Timer = ReturnType<typeof setInterval>;

export default ({ server, className }: { server: Server; className?: string }) => {
    const interval = useRef<Timer>(null) as React.MutableRefObject<Timer>;
    const [isSuspended, setIsSuspended] = useState(server.status === 'suspended');
    const [stats, setStats] = useState<ServerStats | null>(null);

    const getStats = () =>
        getServerResourceUsage(server.uuid)
            .then((data) => setStats(data))
            .catch((error) => console.error(error));

    useEffect(() => {
        setIsSuspended(stats?.isSuspended || server.status === 'suspended');
    }, [stats?.isSuspended, server.status]);

    useEffect(() => {
        if (isSuspended) return;

        getStats().then(() => {
            interval.current = setInterval(() => getStats(), 30000);
        });

        return () => {
            interval.current && clearInterval(interval.current);
        };
    }, [isSuspended]);

    const alarms = { cpu: false, memory: false, disk: false };
    if (stats) {
        alarms.cpu = server.limits.cpu === 0 ? false : stats.cpuUsagePercent >= server.limits.cpu * 0.9;
        alarms.memory = isAlarmState(stats.memoryUsageInBytes, server.limits.memory);
        alarms.disk = server.limits.disk === 0 ? false : isAlarmState(stats.diskUsageInBytes, server.limits.disk);
    }

    const diskLimit = server.limits.disk !== 0 ? bytesToString(mbToBytes(server.limits.disk)) : '∞';
    const memoryLimit = server.limits.memory !== 0 ? bytesToString(mbToBytes(server.limits.memory)) : '∞';
    const cpuLimit = server.limits.cpu !== 0 ? `${server.limits.cpu}%` : '∞';

    const defaultAllocation = server.allocations.find((alloc) => alloc.isDefault);
    const address = defaultAllocation
        ? `${defaultAllocation.alias || ip(defaultAllocation.ip)}:${defaultAllocation.port}`
        : '—';

    const locationLabel = server.locationLong || server.location || '—';
    const rowTitle = server.description ? `${server.name} — ${server.description}` : server.name;

    return (
        <StatusIndicatorBox as={Link} to={`/server/${server.id}`} className={className} $status={stats?.status} title={rowTitle}>
            <Cell>
                <FontAwesomeIcon icon={faServer} css={tw`text-neutral-500 flex-shrink-0 text-xs`} fixedWidth />
                <span css={tw`truncate font-semibold text-sm`}>{server.name}</span>
            </Cell>

            <Cell $hideBelow={'sm'}>
                <FontAwesomeIcon icon={faGlobe} css={tw`text-neutral-500 text-xs flex-shrink-0`} fixedWidth />
                <span css={[navText, tw`text-xs truncate opacity-80`]} title={locationLabel}>
                    {locationLabel}
                </span>
            </Cell>

            <Cell $hideBelow={'md'}>
                <FontAwesomeIcon icon={faEthernet} css={tw`text-neutral-500 text-xs flex-shrink-0`} fixedWidth />
                <span css={[navText, tw`text-xs truncate opacity-80 font-mono tabular-nums`]} title={address}>
                    {address}
                </span>
            </Cell>

            {!stats || isSuspended ? (
                <Cell
                    css={css`
                        ${tw`flex justify-start lg:justify-end`};
                        @media (min-width: 1024px) {
                            grid-column: 4 / span 3;
                        }
                    `}
                >
                    {isSuspended ? (
                        <span css={tw`bg-red-500 rounded px-2 py-0.5 text-red-100 text-xs`}>
                            {server.status === 'suspended' ? 'Suspended' : 'Connection Error'}
                        </span>
                    ) : server.isTransferring || server.status ? (
                        <span css={tw`bg-neutral-500 rounded px-2 py-0.5 text-neutral-100 text-xs`}>
                            {server.isTransferring
                                ? 'Transferring'
                                : server.status === 'installing'
                                  ? 'Installing'
                                  : server.status === 'restoring_backup'
                                    ? 'Restoring Backup'
                                    : 'Unavailable'}
                        </span>
                    ) : (
                        <Spinner size={'small'} />
                    )}
                </Cell>
            ) : (
                <>
                    <Metric
                        icon={faMicrochip}
                        value={`${stats.cpuUsagePercent.toFixed(1)}%`}
                        limit={cpuLimit}
                        alarm={alarms.cpu}
                    />
                    <Metric
                        icon={faMemory}
                        value={bytesToString(stats.memoryUsageInBytes)}
                        limit={memoryLimit}
                        alarm={alarms.memory}
                    />
                    <Metric icon={faHdd} value={bytesToString(stats.diskUsageInBytes)} limit={diskLimit} alarm={alarms.disk} />
                </>
            )}

            <div className={'status-bar'} />
        </StatusIndicatorBox>
    );
};
