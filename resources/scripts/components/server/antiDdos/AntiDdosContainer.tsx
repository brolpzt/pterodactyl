import React, { useMemo, useState } from 'react';
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
import ChartBlock from '@/components/server/console/ChartBlock';
import { ServerContext } from '@/state/server';
import { useStoreState } from 'easy-peasy';
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

const ColumnLabel = ({ label, tip }: { label: string; tip: string }) => (
    <Tooltip placement={'top'} content={tip}>
        <span css={tw`cursor-help border-b border-dashed border-neutral-500`}>{label}</span>
    </Tooltip>
);

export default () => {
    const history = useHistory();
    const match = useRouteMatch();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [period, setPeriod] = useState<PeriodValue>('7d');
    const [page, setPage] = useState(1);

    const onPeriodChange = (next: PeriodValue) => {
        setPeriod(next);
        setPage(1);
    };

    const openEvent = (eventId: string) => {
        history.push(`${match.url.replace(/\/$/, '')}/${eventId}`);
    };

    const { data, error, isValidating } = useSWR(
        rootAdmin ? ['server:anti-ddos', uuid, period] : null,
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
        const ordered = [...events]
            .filter((event) => event.attack_start_time)
            .sort((a, b) => {
                const aTime = parseISO(a.attack_start_time!).getTime();
                const bTime = parseISO(b.attack_start_time!).getTime();
                return aTime - bTime;
            });

        const short = ['15m', '1h', '6h', '24h'].includes(period);
        const labels = ordered.map((event) =>
            format(parseISO(event.attack_start_time!), short ? 'dd/MM HH:mm' : 'dd/MM')
        );
        const powers = ordered.map((event) => bpsToMbps(event.attack_power_bps));
        const suggestedMax = Math.max(0.1, ...powers, 0.1);

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
                            const index = items[0]?.dataIndex ?? 0;
                            const event = ordered[index];
                            return event ? formatWhen(event.attack_start_time) : '';
                        },
                        label: (item) => {
                            const event = ordered[item.dataIndex];
                            if (!event) return '';
                            return ` Potência: ${formatBps(event.attack_power_bps)}`;
                        },
                        afterLabel: (item) => {
                            const event = ordered[item.dataIndex];
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
                    radius: ordered.length <= 24 ? 3 : 0,
                    hoverRadius: 5,
                },
                line: { tension: 0.25 },
            },
            onClick: (_event, elements) => {
                const index = elements[0]?.index;
                if (index === undefined) return;
                const event = ordered[index];
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
                        tension: 0.25,
                    },
                ],
            },
            options,
            count: ordered.length,
        };
    }, [events, period, history, match.url]);

    if (!rootAdmin) {
        return (
            <ServerContentBlock title={'Anti-DDoS'}>
                <TitledGreyBox title={'Anti-DDoS (beta)'} icon={faShieldVirus}>
                    <p css={[cardValueText, tw`m-0`]}>Esta página está disponível apenas para administradores.</p>
                </TitledGreyBox>
            </ServerContentBlock>
        );
    }

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
                    <h2 css={tw`text-xl font-header font-medium text-neutral-100 m-0`}>Anti-DDoS (beta)</h2>
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
                    <p className={'m-0 font-medium text-neutral-100'}>Mostra apenas ataques às portas deste servidor</p>
                    <p className={'m-0 mt-1.5 text-neutral-200 leading-relaxed'}>
                        Ataques costumam atingir várias portas e IPs ao mesmo tempo; a coluna «% neste servidor» indica
                        a parte que chegou aqui. Eventos em andamento ainda podem ser atualizados.
                    </p>
                </div>
            </Alert>

            {!data?.supported ? (
                <TitledGreyBox title={'Proteção DDoS'} icon={faShieldVirus}>
                    <p css={[cardValueText, tw`m-0`]}>Este servidor não tem um IP com Proteção DDoS associado.</p>
                </TitledGreyBox>
            ) : (
                <>
                    {chart.count === 0 ? (
                        <TitledGreyBox title={'Potência dos ataques'} icon={faShieldVirus} className={'mb-4'}>
                            <p css={[emptyStateText, tw`py-2 m-0`]}>
                                Sem eventos neste período para desenhar o gráfico.
                            </p>
                        </TitledGreyBox>
                    ) : (
                        <div css={tw`mb-4`}>
                            <ChartBlock title={'Potência dos ataques (período selecionado)'}>
                                <div css={tw`h-64 cursor-pointer`}>
                                    <Line data={chart.data} options={chart.options} />
                                </div>
                            </ChartBlock>
                        </div>
                    )}

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
