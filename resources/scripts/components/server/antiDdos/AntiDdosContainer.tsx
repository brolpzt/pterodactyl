import React, { useEffect, useMemo, useState } from 'react';
import useSWR from 'swr';
import { format, parseISO } from 'date-fns';
import { Line } from 'react-chartjs-2';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip as ChartTooltip,
} from 'chart.js';
import type { ChartOptions } from 'chart.js';
import tw, { theme } from 'twin.macro';
import { faShieldVirus } from '@fortawesome/free-solid-svg-icons';
import { useHistory, useRouteMatch } from 'react-router-dom';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Button from '@/components/elements/Button';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import PeriodSelector, { PeriodOption, PeriodValue } from '@/components/elements/PeriodSelector';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import { Alert } from '@/components/elements/alert';
import { ServerContext } from '@/state/server';
import { AntiDdosEventSummary, getAntiDdosEvents } from '@/api/server/antiDdos/getAntiDdosEvents';
import { bpsToMbps, formatBps, formatPps, formatWhen } from '@/components/server/antiDdos/format';
import { cardLabelText, cardRowBorder, cardValueText, emptyStateText } from '@/assets/css/cardTheme';
import type { PaginationDataSet } from '@/api/http';
import { hexToRgba } from '@/lib/helpers';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, ChartTooltip);

const PERIOD_OPTIONS: PeriodOption[] = [
    { value: '15m', label: '15 min' },
    { value: '1h', label: '1 h' },
    { value: '6h', label: '6 h' },
    { value: '24h', label: '24 h' },
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
];

const PAGE_SIZE = 10;

const MINUTE = 60_000;

/** Timeline resolution per period: total span, bucket size and x-axis label format. */
const TIMELINE: Record<string, { span: number; bucket: number; label: string }> = {
    '15m': { span: 15 * MINUTE, bucket: MINUTE, label: 'HH:mm' },
    '1h': { span: 60 * MINUTE, bucket: 5 * MINUTE, label: 'HH:mm' },
    '6h': { span: 6 * 60 * MINUTE, bucket: 15 * MINUTE, label: 'HH:mm' },
    '24h': { span: 24 * 60 * MINUTE, bucket: 60 * MINUTE, label: 'HH:mm' },
    '7d': { span: 7 * 24 * 60 * MINUTE, bucket: 6 * 60 * MINUTE, label: 'dd/MM HH:mm' },
    '30d': { span: 30 * 24 * 60 * MINUTE, bucket: 24 * 60 * MINUTE, label: 'dd/MM' },
};

interface TimelineBucket {
    start: number;
    end: number;
    events: AntiDdosEventSummary[];
    peak: AntiDdosEventSummary | null;
}

const ColumnLabel = ({ label, tip }: { label: string; tip: string }) => (
    <Tooltip placement={'top'} content={tip}>
        <span css={tw`cursor-help border-b border-dashed border-neutral-500`}>{label}</span>
    </Tooltip>
);

export default () => {
    const history = useHistory();
    const match = useRouteMatch();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [period, setPeriod] = useState<PeriodValue>('30d');
    const [page, setPage] = useState(1);
    const [now, setNow] = useState(() => Date.now());

    // Keep the timeline moving up to "now" even when no new events arrive.
    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), MINUTE);
        return () => clearInterval(timer);
    }, []);

    const onPeriodChange = (next: PeriodValue) => {
        setPeriod(next);
        setPage(1);
    };

    const openEvent = (eventId: string) => {
        history.push(`${match.url.replace(/\/$/, '')}/${eventId}`);
    };

    const { data, error, isValidating } = useSWR(
        ['server:anti-ddos', uuid, period],
        () => getAntiDdosEvents(uuid, period),
        { refreshInterval: 60_000, revalidateOnFocus: true }
    );

    const events = data?.events || [];
    const totalPages = Math.max(1, Math.ceil(events.length / PAGE_SIZE));
    const currentPage = Math.min(page, totalPages);
    const pageEvents = useMemo(() => {
        const start = (currentPage - 1) * PAGE_SIZE;
        return events.slice(start, start + PAGE_SIZE);
    }, [events, currentPage]);

    const pagination: PaginationDataSet = {
        total: events.length,
        count: pageEvents.length,
        perPage: PAGE_SIZE,
        currentPage,
        totalPages,
    };

    const chart = useMemo(() => {
        const config = TIMELINE[period] ?? TIMELINE['30d'];
        // Align buckets to the viewer's local clock so daily buckets start at local midnight.
        const offset = new Date(now).getTimezoneOffset() * MINUTE;
        const first = Math.floor((now - config.span - offset) / config.bucket) * config.bucket + offset;
        const buckets: TimelineBucket[] = [];
        for (let start = first; start <= now; start += config.bucket) {
            buckets.push({ start, end: start + config.bucket, events: [], peak: null });
        }

        events.forEach((event) => {
            if (!event.attack_start_time) return;
            const time = parseISO(event.attack_start_time).getTime();
            const index = Math.floor((time - first) / config.bucket);
            const bucket = buckets[index];
            if (!bucket) return;
            bucket.events.push(event);
            if (!bucket.peak || (event.attack_power_bps ?? 0) > (bucket.peak.attack_power_bps ?? 0)) {
                bucket.peak = event;
            }
        });

        const labels = buckets.map((bucket) => format(bucket.start, config.label));
        const powers = buckets.map((bucket) => (bucket.peak ? bpsToMbps(bucket.peak.attack_power_bps) : 0));
        const pointRadius = buckets.map((bucket) => (bucket.peak ? 3 : 0));
        const suggestedMax = Math.max(0.1, ...powers);
        const rangeLabel = (bucket: TimelineBucket) =>
            config.bucket >= 24 * 60 * MINUTE
                ? format(bucket.start, 'dd/MM/yyyy')
                : `${format(bucket.start, 'dd/MM HH:mm')} – ${format(Math.min(bucket.end, now), 'HH:mm')}`;

        const options: ChartOptions<'line'> = {
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
                        title: (items) => {
                            const bucket = buckets[items[0]?.dataIndex ?? 0];
                            return bucket ? rangeLabel(bucket) : '';
                        },
                        label: (item) => {
                            const bucket = buckets[item.dataIndex];
                            if (!bucket?.peak) return ' Sem ataques';
                            const count = bucket.events.length;
                            return ` Potência: ${formatBps(bucket.peak.attack_power_bps)}${
                                count > 1 ? ` (maior de ${count} ataques)` : ''
                            }`;
                        },
                        afterLabel: (item) => {
                            const event = buckets[item.dataIndex]?.peak;
                            if (!event) return '';
                            const target =
                                (event.ports || []).map((port) => `${event.targeted_ip}:${port}`).join(', ') ||
                                event.targeted_ip;
                            return ` Alvo: ${target}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    type: 'category',
                    grid: {
                        display: false,
                        drawBorder: false,
                    },
                    ticks: {
                        display: true,
                        autoSkip: true,
                        maxTicksLimit: 8,
                        maxRotation: 0,
                        color: theme('colors.gray.200'),
                        font: {
                            family: theme('fontFamily.sans'),
                            size: 10,
                        },
                    },
                },
                y: {
                    min: 0,
                    suggestedMax,
                    type: 'linear',
                    grid: {
                        display: true,
                        color: theme('colors.gray.700'),
                        drawBorder: false,
                    },
                    ticks: {
                        color: theme('colors.gray.200'),
                        font: {
                            family: theme('fontFamily.sans'),
                            size: 11,
                        },
                        callback: (value) => {
                            const n = typeof value === 'string' ? Number(value) : value;
                            if (n >= 1000) return `${(n / 1000).toFixed(1)} Gbps`;
                            if (n >= 1) return `${n.toFixed(n >= 100 ? 0 : 1)} Mbps`;
                            return `${(n * 1000).toFixed(0)} Kbps`;
                        },
                    },
                },
            },
            elements: {
                point: {
                    hoverRadius: 5,
                },
                line: { tension: 0.25 },
            },
            onClick: (_event, elements) => {
                const index = elements[0]?.index;
                if (index === undefined) return;
                const event = buckets[index]?.peak;
                if (event) {
                    history.push(`${match.url.replace(/\/$/, '')}/${event.id}`);
                }
            },
        };

        return {
            data: {
                labels,
                datasets: [
                    {
                        label: 'Potência (Mbps)',
                        fill: true,
                        data: powers,
                        borderColor: theme('colors.red.400'),
                        backgroundColor: hexToRgba(theme('colors.red.700'), 0.35),
                        pointBackgroundColor: theme('colors.red.300'),
                        pointRadius,
                        tension: 0.25,
                    },
                ],
            },
            options,
        };
    }, [events, period, now, history, match.url]);

    if ((!data && !error) || (error && isValidating && !data)) {
        return (
            <ServerContentBlock title={'Anti-DDoS'}>
                <Spinner size={'large'} centered />
            </ServerContentBlock>
        );
    }

    const endpointsLabel = data?.endpoints?.length
        ? data.endpoints.map((e) => `${e.ip}:${e.port}`).join(', ')
        : data?.ips?.length
        ? data.ips.join(', ')
        : null;

    return (
        <ServerContentBlock title={'Anti-DDoS'}>
            <div css={tw`mb-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3`}>
                <div>
                    <h2 css={tw`flex items-center gap-2 text-xl font-header font-medium text-neutral-100 m-0`}>
                        Anti-DDoS
                        <span
                            css={tw`text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded bg-yellow-500/20 text-yellow-300`}
                        >
                            Beta
                        </span>
                    </h2>
                    <p css={[cardLabelText, tw`text-sm mt-1 mb-0`]}>
                        {endpointsLabel ? `Portas monitoradas: ${endpointsLabel}` : 'Portas deste servidor'}
                    </p>
                </div>
                <PeriodSelector value={period} options={PERIOD_OPTIONS} onChange={onPeriodChange} />
            </div>

            <Alert
                type={'warning'}
                className={'mb-4 text-sm items-start [&>svg]:mt-0.5 [&>svg]:shrink-0 [&>svg]:w-5 [&>svg]:h-5'}
            >
                <div>
                    <p className={'m-0 font-medium text-neutral-100'}>Esta tela está em beta</p>
                    <p className={'m-0 mt-1.5 text-neutral-200 leading-relaxed'}>
                        Os dados podem ter atrasos ou imprecisões, e a página ainda pode mudar.
                    </p>
                </div>
            </Alert>

            <Alert
                type={'warning'}
                className={'mb-4 text-sm items-start [&>svg]:mt-0.5 [&>svg]:shrink-0 [&>svg]:w-5 [&>svg]:h-5'}
            >
                <div>
                    <p className={'m-0 font-medium text-neutral-100'}>Mostra apenas ataques às portas deste servidor</p>
                    <p className={'m-0 mt-1.5 text-neutral-200 leading-relaxed'}>
                        Ataques costumam atingir várias portas e IPs ao mesmo tempo; a coluna{' '}
                        <strong className={'font-semibold text-neutral-100'}>% neste servidor</strong> indica a parte
                        que chegou aqui no seu servidor de jogo. Eventos em andamento ainda podem ser atualizados.
                    </p>
                </div>
            </Alert>

            {!data?.supported ? (
                <TitledGreyBox title={'Proteção DDoS'} icon={faShieldVirus}>
                    <p css={[cardValueText, tw`m-0`]}>Este servidor não tem um IP com Proteção DDoS associado.</p>
                </TitledGreyBox>
            ) : (
                <>
                    <TitledGreyBox title={'Potência dos ataques'} icon={faShieldVirus} className={'mb-4'}>
                        <div css={tw`h-64 cursor-pointer`}>
                            <Line data={chart.data} options={chart.options} />
                        </div>
                    </TitledGreyBox>

                    <TitledGreyBox title={'Eventos'} icon={faShieldVirus}>
                        {events.length === 0 ? (
                            <p css={[emptyStateText, tw`py-4 m-0`]}>
                                Sem eventos neste período. Atualizado a cada 5 minutos.
                            </p>
                        ) : (
                            <>
                                <div css={tw`overflow-x-auto -mx-1`}>
                                    <table css={tw`w-full text-sm`}>
                                        <thead>
                                            <tr css={[tw`border-b`, cardRowBorder]}>
                                                <th css={[cardLabelText, tw`text-left py-2 font-medium`]}>
                                                    <ColumnLabel
                                                        label={'Início'}
                                                        tip={'Momento em que o ataque foi detectado pela Proteção DDoS'}
                                                    />
                                                </th>
                                                <th
                                                    css={[
                                                        cardLabelText,
                                                        tw`text-left py-2 font-medium hidden md:table-cell`,
                                                    ]}
                                                >
                                                    <ColumnLabel
                                                        label={'Alvo'}
                                                        tip={'IP e portas deste servidor atingidas pelo ataque'}
                                                    />
                                                </th>
                                                <th css={[cardLabelText, tw`text-left py-2 font-medium`]}>
                                                    <ColumnLabel
                                                        label={'Estado'}
                                                        tip={'Se o ataque ainda está em andamento ou já terminou'}
                                                    />
                                                </th>
                                                <th css={[cardLabelText, tw`text-right py-2 font-medium`]}>
                                                    <ColumnLabel
                                                        label={'Potência'}
                                                        tip={
                                                            'Volume máximo do ataque nas portas deste servidor (bits por segundo)'
                                                        }
                                                    />
                                                </th>
                                                <th
                                                    css={[
                                                        cardLabelText,
                                                        tw`text-right py-2 font-medium hidden sm:table-cell`,
                                                    ]}
                                                >
                                                    <ColumnLabel
                                                        label={'Pacotes'}
                                                        tip={
                                                            'Taxa máxima de pacotes por segundo nas portas deste servidor'
                                                        }
                                                    />
                                                </th>
                                                <th
                                                    css={[
                                                        cardLabelText,
                                                        tw`text-right py-2 font-medium hidden lg:table-cell`,
                                                    ]}
                                                >
                                                    <ColumnLabel
                                                        label={'% neste servidor'}
                                                        tip={
                                                            'Porcentagem do tráfego do ataque (no IP) que atingiu as portas deste servidor'
                                                        }
                                                    />
                                                </th>
                                                <th css={[cardLabelText, tw`text-right py-2 font-medium`]}>
                                                    <ColumnLabel label={'Ações'} tip={'Abrir o detalhe deste evento'} />
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {pageEvents.map((event: AntiDdosEventSummary) => {
                                                const target =
                                                    (event.ports || [])
                                                        .map((port) => `${event.targeted_ip}:${port}`)
                                                        .join(', ') || event.targeted_ip;

                                                return (
                                                    <tr
                                                        key={event.id}
                                                        css={[
                                                            tw`border-b last:border-0 cursor-pointer transition hover:bg-neutral-700/30`,
                                                            cardRowBorder,
                                                        ]}
                                                        onClick={() => openEvent(event.id)}
                                                    >
                                                        <td css={[cardValueText, tw`py-3 pr-3 whitespace-nowrap`]}>
                                                            {formatWhen(event.attack_start_time)}
                                                        </td>
                                                        <td
                                                            css={[
                                                                cardValueText,
                                                                tw`py-3 pr-3 font-mono text-xs hidden md:table-cell`,
                                                            ]}
                                                        >
                                                            {target}
                                                        </td>
                                                        <td css={tw`py-3 pr-3`}>
                                                            <span
                                                                css={[
                                                                    tw`inline-block text-xs px-2 py-0.5 rounded`,
                                                                    event.ongoing
                                                                        ? tw`bg-red-500/20 text-red-300`
                                                                        : tw`bg-neutral-600/40 text-neutral-200`,
                                                                ]}
                                                            >
                                                                {event.ongoing ? 'Em andamento' : 'Terminado'}
                                                            </span>
                                                        </td>
                                                        <td
                                                            css={[
                                                                cardValueText,
                                                                tw`py-3 pr-3 text-right whitespace-nowrap`,
                                                            ]}
                                                        >
                                                            {formatBps(event.attack_power_bps)}
                                                        </td>
                                                        <td
                                                            css={[
                                                                cardValueText,
                                                                tw`py-3 pr-3 text-right whitespace-nowrap hidden sm:table-cell`,
                                                            ]}
                                                        >
                                                            {formatPps(event.attack_power_pps)}
                                                        </td>
                                                        <td
                                                            css={[
                                                                cardValueText,
                                                                tw`py-3 pr-3 text-right whitespace-nowrap hidden lg:table-cell`,
                                                            ]}
                                                        >
                                                            {event.share_percentage != null
                                                                ? `${event.share_percentage}%`
                                                                : '—'}
                                                        </td>
                                                        <td
                                                            css={tw`py-3 text-right`}
                                                            onClick={(e) => e.stopPropagation()}
                                                        >
                                                            <Button
                                                                isSecondary
                                                                size={'xsmall'}
                                                                onClick={() => openEvent(event.id)}
                                                            >
                                                                Ver
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                                <PaginationFooter pagination={pagination} onPageSelect={setPage} />
                            </>
                        )}
                    </TitledGreyBox>
                </>
            )}

            {error && <p css={tw`text-sm text-red-400 mt-3 mb-0`}>Não foi possível atualizar a lista de eventos.</p>}
        </ServerContentBlock>
    );
};
