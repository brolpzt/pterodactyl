import React, { useEffect, useMemo, useState } from 'react';
import useSWR from 'swr';
import { format, formatDistanceToNowStrict, parseISO } from 'date-fns';
import { ptBR } from 'date-fns/locale';
import { Bar } from 'react-chartjs-2';
import { BarElement, CategoryScale, Chart as ChartJS, LinearScale, Tooltip as ChartTooltip } from 'chart.js';
import type { ChartOptions } from 'chart.js';
import tw, { theme } from 'twin.macro';
import styled from 'styled-components/macro';
import { faRocket, faUsers } from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import PeriodSelector, { PeriodOption, PeriodValue } from '@/components/elements/PeriodSelector';
import { Alert } from '@/components/elements/alert';
import { ServerContext } from '@/state/server';
import { getServerBoost, ServerBoostConnection } from '@/api/server/boost/getServerBoost';
import { cardLabelText, cardRowBorder, cardSurface, cardValueText, emptyStateText } from '@/assets/css/cardTheme';
import { glassCardShell, glassContentLayer } from '@/assets/css/glassPanel';
import { hgRadiusSm } from '@/assets/css/borderTheme';
import type { PaginationDataSet } from '@/api/http';

ChartJS.register(CategoryScale, LinearScale, BarElement, ChartTooltip);

const PERIOD_OPTIONS: PeriodOption[] = [
    { value: '24h', label: '24 h' },
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
];

const PERIOD_LABELS: Record<string, string> = { '24h': '24 h', '7d': '7 dias', '30d': '30 dias' };

const PAGE_SIZE = 10;
const HOUR = 3_600_000;

/** Chart resolution per period: total span, bucket size and x-axis label format. */
const TIMELINE: Record<string, { span: number; bucket: number; label: string }> = {
    '24h': { span: 24 * HOUR, bucket: HOUR, label: 'HH:mm' },
    '7d': { span: 7 * 24 * HOUR, bucket: 6 * HOUR, label: 'dd/MM HH:mm' },
    '30d': { span: 30 * 24 * HOUR, bucket: 24 * HOUR, label: 'dd/MM' },
};

const StatCardShell = styled.div.attrs({ className: 'hg-glass-card' })`
    ${glassCardShell};
    ${hgRadiusSm};
`;

const StatCardInner = styled.div`
    ${glassContentLayer};
    ${cardSurface};
    ${tw`relative z-10 p-4`};
`;

const SummaryCard = ({ label, value, hint }: { label: string; value: string; hint?: string }) => (
    <StatCardShell>
        <StatCardInner>
            <p css={[cardLabelText, tw`text-xs uppercase tracking-wide m-0 mb-1`]}>{label}</p>
            <p css={[cardValueText, tw`text-2xl font-medium m-0`]}>{value}</p>
            {hint && <p css={[cardLabelText, tw`text-xs m-0 mt-1`]}>{hint}</p>}
        </StatCardInner>
    </StatCardShell>
);

const plural = (n: number, one: string, many: string) => `${n.toLocaleString('pt-BR')} ${n === 1 ? one : many}`;

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [period, setPeriod] = useState<PeriodValue>('7d');
    const [page, setPage] = useState(1);
    const [now, setNow] = useState(() => Date.now());

    // Keep the chart moving up to "now" even when no new connections arrive.
    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 60_000);
        return () => clearInterval(timer);
    }, []);

    const { data, error, isValidating } = useSWR(['server:boost', uuid, period], () => getServerBoost(uuid, period), {
        refreshInterval: 30_000,
        revalidateOnFocus: true,
    });

    const onPeriodChange = (next: PeriodValue) => {
        setPeriod(next);
        setPage(1);
    };

    const connections = data?.connections || [];
    const totalPages = Math.max(1, Math.ceil(connections.length / PAGE_SIZE));
    const currentPage = Math.min(page, totalPages);
    const pageRows = useMemo(
        () => connections.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE),
        [connections, currentPage]
    );
    const pagination: PaginationDataSet = {
        total: connections.length,
        count: pageRows.length,
        perPage: PAGE_SIZE,
        currentPage,
        totalPages,
    };

    const chart = useMemo(() => {
        const config = TIMELINE[period] ?? TIMELINE['7d'];
        // Buckets follow the viewer's clock so daily bars start at local midnight.
        const offset = new Date(now).getTimezoneOffset() * 60_000;
        const first = Math.floor((now - config.span - offset) / config.bucket) * config.bucket + offset + config.bucket;
        const buckets: Array<{ start: number; count: number }> = [];
        for (let start = first; start <= now; start += config.bucket) {
            buckets.push({ start, count: 0 });
        }
        (data?.hourly || []).forEach(({ hour, count }) => {
            const bucket = buckets[Math.floor((parseISO(hour).getTime() - first) / config.bucket)];
            if (bucket) bucket.count += count;
        });

        const rangeLabel = (start: number) =>
            config.bucket >= 24 * HOUR
                ? format(start, 'dd/MM/yyyy')
                : `${format(start, 'dd/MM HH:mm')} – ${format(Math.min(start + config.bucket, now), 'HH:mm')}`;

        const options: ChartOptions<'bar'> = {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                title: { display: false },
                tooltip: {
                    enabled: true,
                    intersect: false,
                    mode: 'index',
                    callbacks: {
                        title: (items) => rangeLabel(buckets[items[0]?.dataIndex ?? 0]?.start ?? now),
                        label: (item) => ` ${plural(Number(item.raw), 'jogador', 'jogadores')} via boost`,
                    },
                },
            },
            scales: {
                x: {
                    type: 'category',
                    grid: { display: false, drawBorder: false },
                    ticks: {
                        autoSkip: true,
                        maxTicksLimit: 8,
                        maxRotation: 0,
                        color: theme('colors.gray.200'),
                        font: { family: theme('fontFamily.sans'), size: 10 },
                    },
                },
                y: {
                    min: 0,
                    suggestedMax: 1,
                    type: 'linear',
                    grid: { display: true, color: theme('colors.gray.700'), drawBorder: false },
                    ticks: {
                        precision: 0,
                        color: theme('colors.gray.200'),
                        font: { family: theme('fontFamily.sans'), size: 11 },
                    },
                },
            },
        };

        return {
            data: {
                labels: buckets.map((b) => format(b.start, config.label)),
                datasets: [
                    {
                        label: 'Jogadores via boost',
                        data: buckets.map((b) => b.count),
                        backgroundColor: theme('colors.primary.400'),
                        hoverBackgroundColor: theme('colors.primary.300'),
                        borderRadius: { topLeft: 4, topRight: 4 },
                        borderSkipped: 'bottom' as const,
                        maxBarThickness: 28,
                    },
                ],
            },
            options,
        };
    }, [data?.hourly, period, now]);

    if ((!data && !error) || (error && isValidating && !data)) {
        return (
            <ServerContentBlock title={'Server Boost'}>
                <Spinner size={'large'} centered />
            </ServerContentBlock>
        );
    }

    const periodLabel = PERIOD_LABELS[data?.period || period] || period;
    const last = data?.last_connected_at ? parseISO(data.last_connected_at) : null;

    return (
        <ServerContentBlock title={'Server Boost'}>
            <div css={tw`mb-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3`}>
                <div>
                    <h2 css={tw`text-xl font-header font-medium text-neutral-100 m-0`}>Server Boost</h2>
                    <p css={[cardLabelText, tw`text-sm mt-1 mb-0`]}>
                        Jogadores que o sistema de boost enviou para este servidor.
                    </p>
                </div>
                <PeriodSelector value={period} options={PERIOD_OPTIONS} onChange={onPeriodChange} />
            </div>

            {data && !data.listed && (
                <Alert
                    type={'info'}
                    className={'mb-4 text-sm items-start [&>svg]:mt-0.5 [&>svg]:shrink-0 [&>svg]:w-5 [&>svg]:h-5'}
                >
                    <p className={'m-0 text-neutral-100'}>
                        No momento este servidor não está recebendo jogadores do boost. O histórico abaixo continua
                        disponível.
                    </p>
                </Alert>
            )}

            <div css={tw`grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4`}>
                <SummaryCard label={`Conexões (${periodLabel})`} value={(data?.total ?? 0).toLocaleString('pt-BR')} />
                <SummaryCard
                    label={`Jogadores únicos (${periodLabel})`}
                    value={(data?.players ?? 0).toLocaleString('pt-BR')}
                />
                <SummaryCard
                    label={'Última conexão'}
                    value={last ? formatDistanceToNowStrict(last, { locale: ptBR, addSuffix: true }) : '—'}
                    hint={last ? format(last, 'dd/MM/yyyy HH:mm') : undefined}
                />
            </div>

            <TitledGreyBox title={'Jogadores via boost'} icon={faRocket} className={'mb-4'}>
                <div css={tw`h-64`}>
                    <Bar data={chart.data} options={chart.options} />
                </div>
            </TitledGreyBox>

            <TitledGreyBox title={'Jogadores conectados via boost'} icon={faUsers} bodyClassName={'!pt-1.5'}>
                {connections.length === 0 ? (
                    <p css={[emptyStateText, tw`py-4 m-0`]}>Nenhum jogador chegou pelo boost neste período.</p>
                ) : (
                    <>
                        <div css={tw`overflow-x-auto -mx-1`}>
                            <table css={tw`w-full text-sm`}>
                                <thead>
                                    <tr css={[tw`border-b`, cardRowBorder]}>
                                        <th css={[cardLabelText, tw`text-left py-1.5 font-medium`]}>Quando</th>
                                        <th css={[cardLabelText, tw`text-left py-1.5 font-medium`]}>Jogador</th>
                                        <th
                                            css={[cardLabelText, tw`text-left py-1.5 font-medium hidden sm:table-cell`]}
                                        >
                                            SteamID
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pageRows.map((row: ServerBoostConnection) => {
                                        const at = parseISO(row.connected_at);
                                        return (
                                            <tr key={row.id} css={[tw`border-b last:border-0`, cardRowBorder]}>
                                                <td css={[cardValueText, tw`py-3 pr-3 whitespace-nowrap`]}>
                                                    {format(at, 'dd/MM/yyyy HH:mm')}
                                                    <span css={[cardLabelText, tw`ml-2 text-xs`]}>
                                                        {formatDistanceToNowStrict(at, {
                                                            locale: ptBR,
                                                            addSuffix: true,
                                                        })}
                                                    </span>
                                                </td>
                                                <td css={[cardValueText, tw`py-3 pr-3 break-all`]}>
                                                    {row.player || '—'}
                                                </td>
                                                <td
                                                    css={[
                                                        cardValueText,
                                                        tw`py-3 pr-3 font-mono text-xs hidden sm:table-cell`,
                                                    ]}
                                                >
                                                    {row.steam_id || '—'}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <PaginationFooter pagination={pagination} onPageSelect={setPage} />
                        {connections.length >= 200 && (
                            <p css={[cardLabelText, tw`text-xs mt-2 mb-0`]}>Mostrando as 200 conexões mais recentes.</p>
                        )}
                    </>
                )}
            </TitledGreyBox>

            {error && <p css={tw`text-sm text-red-400 mt-3 mb-0`}>Não foi possível atualizar os dados do boost.</p>}
        </ServerContentBlock>
    );
};
