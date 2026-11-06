import React, { useMemo, useState } from 'react';
import useSWR from 'swr';
import { format, parseISO } from 'date-fns';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import { Line } from 'react-chartjs-2';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { theme } from 'twin.macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import ChartBlock from '@/components/server/console/ChartBlock';
import PeriodSelector, { PeriodOption, PeriodValue } from '@/components/elements/PeriodSelector';
import { ServerContext } from '@/state/server';
import { useStoreState } from 'easy-peasy';
import {
    AntiDdosEventSummary,
    getAntiDdosEvent,
    getAntiDdosEvents,
} from '@/api/server/antiDdos/getAntiDdosEvents';
import { cardLabelText, cardSurface, cardValueText } from '@/assets/css/cardTheme';
import { glassCardShell, glassContentLayer } from '@/assets/css/glassPanel';
import { hgRadiusSm } from '@/assets/css/borderTheme';
import { hexToRgba } from '@/lib/helpers';
import type { ChartOptions } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip);

const PERIOD_OPTIONS: PeriodOption[] = [
    { value: '15m', label: '15 min' },
    { value: '1h', label: '1 h' },
    { value: '6h', label: '6 h' },
    { value: '24h', label: '24 h' },
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
];

const CardShell = styled.div.attrs({ className: 'hg-glass-card' })`
    ${glassCardShell};
    ${hgRadiusSm};
`;

const CardInner = styled.div`
    ${glassContentLayer};
    ${cardSurface};
    ${tw`relative z-10 p-4`};
`;

const formatBps = (value: number | null | undefined): string => {
    if (value === null || value === undefined) return '—';
    const units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
    let n = value;
    let i = 0;
    while (n >= 1000 && i < units.length - 1) {
        n /= 1000;
        i++;
    }
    return `${n.toFixed(n >= 100 ? 0 : 1)} ${units[i]}`;
};

const formatPps = (value: number | null | undefined): string => {
    if (value === null || value === undefined) return '—';
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)} Mpps`;
    if (value >= 1_000) return `${(value / 1_000).toFixed(1)} Kpps`;
    return `${Math.round(value)} pps`;
};

const formatWhen = (value: string | null | undefined): string => {
    if (!value) return '—';
    try {
        return format(parseISO(value), 'dd/MM/yyyy HH:mm');
    } catch {
        return value;
    }
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [period, setPeriod] = useState<PeriodValue>('7d');

    const onPeriodChange = (next: PeriodValue) => {
        setPeriod(next);
        setSelectedId(null);
    };

    const { data, error, isValidating } = useSWR(
        rootAdmin ? ['server:anti-ddos', uuid, period] : null,
        () => getAntiDdosEvents(uuid, period),
        { refreshInterval: 60_000, revalidateOnFocus: true }
    );

    const {
        data: detail,
        error: detailError,
        isValidating: detailLoading,
    } = useSWR(
        rootAdmin && selectedId ? ['server:anti-ddos-detail', uuid, selectedId] : null,
        () => getAntiDdosEvent(uuid, selectedId!),
        { revalidateOnFocus: false }
    );

    const trafficChart = useMemo(() => {
        const points = (detail?.attack_traffic || [])
            .filter((p) => typeof p.interval_start_unix === 'number')
            .slice(-180);

        const labels = points.map((p) => {
            const d = new Date((p.interval_start_unix as number) * 1000);
            return format(d, 'HH:mm:ss');
        });
        const bytes = points.map((p) => Number(p.total_bytes || 0));

        const options: ChartOptions<'line'> = {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true, mode: 'index', intersect: false },
            },
            scales: {
                x: {
                    ticks: {
                        maxTicksLimit: 8,
                        color: theme('colors.gray.200'),
                        font: { size: 10, family: theme('fontFamily.sans') },
                    },
                    grid: { display: false, drawBorder: false },
                },
                y: {
                    min: 0,
                    ticks: {
                        color: theme('colors.gray.200'),
                        callback: (value) => formatBps(typeof value === 'number' ? value * 8 : Number(value) * 8),
                        font: { size: 10, family: theme('fontFamily.sans') },
                    },
                    grid: { color: theme('colors.gray.700'), drawBorder: false },
                },
            },
            elements: { point: { radius: 0 }, line: { tension: 0.2 } },
        };

        return {
            data: {
                labels,
                datasets: [
                    {
                        label: 'Tráfego',
                        fill: true,
                        data: bytes,
                        borderColor: theme('colors.primary.400'),
                        backgroundColor: hexToRgba(theme('colors.primary.700'), 0.4),
                    },
                ],
            },
            options,
        };
    }, [detail?.attack_traffic]);

    if (!rootAdmin) {
        return (
            <ServerContentBlock title={'Anti-DDoS'}>
                <CardShell>
                    <CardInner>
                        <p css={[cardValueText, tw`m-0`]}>Esta página está disponível apenas para administradores.</p>
                    </CardInner>
                </CardShell>
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

    return (
        <ServerContentBlock title={'Anti-DDoS'}>
            <div css={tw`mb-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3`}>
                <div>
                    <h2 css={tw`text-xl font-header font-medium text-gray-100 m-0`}>Anti-DDoS (beta)</h2>
                    <p css={[cardLabelText, tw`text-sm mt-1 mb-0`]}>
                        Eventos Gcore sincronizados localmente para o IP protegido deste servidor.
                        {data?.ips?.length ? ` IP: ${data.ips.join(', ')}` : ''}
                    </p>
                </div>
                <PeriodSelector value={period} options={PERIOD_OPTIONS} onChange={onPeriodChange} />
            </div>

            {!data?.supported ? (
                <CardShell>
                    <CardInner>
                        <p css={[cardValueText, tw`m-0`]}>
                            Este servidor não tem IP Gcore protegido associado. Marca o IP em Node → Gcore IPs.
                        </p>
                    </CardInner>
                </CardShell>
            ) : (
                <div css={tw`grid grid-cols-1 xl:grid-cols-5 gap-4`}>
                    <div css={tw`xl:col-span-2 space-y-3`}>
                        {(data.events || []).length === 0 ? (
                            <CardShell>
                                <CardInner>
                                    <p css={[cardValueText, tw`m-0`]}>
                                        Sem eventos neste período. O sync corre a cada 5 minutos.
                                    </p>
                                </CardInner>
                            </CardShell>
                        ) : (
                            data.events.map((event: AntiDdosEventSummary) => {
                                const active = selectedId === event.id;
                                const ports = (event.top_destination_ports || [])
                                    .slice(0, 3)
                                    .map((p) => `${p.dst_port ?? '?'}/${p.protocol || '?'}`)
                                    .join(', ');

                                return (
                                    <button
                                        key={event.id}
                                        type={'button'}
                                        onClick={() => setSelectedId(event.id)}
                                        css={[
                                            tw`w-full text-left transition`,
                                            active ? tw`ring-1 ring-primary-400` : tw`opacity-95 hover:opacity-100`,
                                        ]}
                                    >
                                        <CardShell>
                                            <CardInner>
                                                <div css={tw`flex items-center justify-between gap-2 mb-1`}>
                                                    <span css={[cardValueText, tw`font-medium m-0`]}>
                                                        {formatWhen(event.attack_start_time)}
                                                    </span>
                                                    <span
                                                        css={[
                                                            tw`text-xs px-2 py-0.5 rounded`,
                                                            event.ongoing
                                                                ? tw`bg-red-500/20 text-red-300`
                                                                : tw`bg-gray-600/40 text-gray-200`,
                                                        ]}
                                                    >
                                                        {event.ongoing ? 'Em curso' : 'Terminado'}
                                                    </span>
                                                </div>
                                                <p css={[cardLabelText, tw`text-sm m-0`]}>
                                                    {formatBps(event.attack_power_bps)} · {formatPps(event.attack_power_pps)}
                                                    {event.source_ip_count != null
                                                        ? ` · ${event.source_ip_count.toLocaleString()} IPs`
                                                        : ''}
                                                </p>
                                                {ports && (
                                                    <p css={[cardLabelText, tw`text-xs m-0 mt-1`]}>Portas: {ports}</p>
                                                )}
                                            </CardInner>
                                        </CardShell>
                                    </button>
                                );
                            })
                        )}
                    </div>

                    <div css={tw`xl:col-span-3`}>
                        {!selectedId ? (
                            <CardShell>
                                <CardInner>
                                    <p css={[cardValueText, tw`m-0`]}>Seleciona um evento à esquerda para ver o detalhe.</p>
                                </CardInner>
                            </CardShell>
                        ) : detailLoading && !detail ? (
                            <Spinner size={'large'} centered />
                        ) : detailError ? (
                            <CardShell>
                                <CardInner>
                                    <p css={[cardValueText, tw`m-0 text-red-300`]}>Falha ao carregar o detalhe do evento.</p>
                                </CardInner>
                            </CardShell>
                        ) : detail ? (
                            <div css={tw`space-y-4`}>
                                <div css={tw`grid grid-cols-2 md:grid-cols-4 gap-3`}>
                                    <CardShell>
                                        <CardInner>
                                            <p css={[cardLabelText, tw`text-xs m-0 mb-1`]}>Potência</p>
                                            <p css={[cardValueText, tw`m-0`]}>{formatBps(detail.attack_power_bps)}</p>
                                        </CardInner>
                                    </CardShell>
                                    <CardShell>
                                        <CardInner>
                                            <p css={[cardLabelText, tw`text-xs m-0 mb-1`]}>Pacotes</p>
                                            <p css={[cardValueText, tw`m-0`]}>{formatPps(detail.attack_power_pps)}</p>
                                        </CardInner>
                                    </CardShell>
                                    <CardShell>
                                        <CardInner>
                                            <p css={[cardLabelText, tw`text-xs m-0 mb-1`]}>IPs origem</p>
                                            <p css={[cardValueText, tw`m-0`]}>
                                                {detail.source_ip_count?.toLocaleString() ?? '—'}
                                            </p>
                                        </CardInner>
                                    </CardShell>
                                    <CardShell>
                                        <CardInner>
                                            <p css={[cardLabelText, tw`text-xs m-0 mb-1`]}>Fim</p>
                                            <p css={[cardValueText, tw`m-0`]}>{formatWhen(detail.attack_end_time)}</p>
                                        </CardInner>
                                    </CardShell>
                                </div>

                                {(detail.attack_traffic || []).length > 0 && (
                                    <ChartBlock title={'Tráfego observado'}>
                                        <div css={tw`h-56`}>
                                            <Line data={trafficChart.data} options={trafficChart.options} />
                                        </div>
                                    </ChartBlock>
                                )}

                                <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                                    <DetailList
                                        title={'Portas de destino'}
                                        rows={(detail.attack_top_destination_ports || []).map(
                                            (p) =>
                                                `${p.dst_port ?? '?'}/${p.protocol || '?'} · ${formatBps(p.bps ?? null)} (${p.bps_percentage ?? 0}%)`
                                        )}
                                    />
                                    <DetailList
                                        title={'Protocolos'}
                                        rows={(detail.attack_top_protocols || []).map(
                                            (p) =>
                                                `${p.protocol || '?'} · ${formatBps(p.bps ?? null)} (${p.bps_percentage ?? 0}%)`
                                        )}
                                    />
                                    <DetailList
                                        title={'Países de origem'}
                                        rows={(detail.attack_top_source_countries || []).map(
                                            (p) => `${p.source_country || '?'} · ${formatBps(p.bps ?? null)}`
                                        )}
                                    />
                                    <DetailList
                                        title={'Top IPs de origem'}
                                        rows={(detail.attack_top_source_ips || []).map(
                                            (p) => `${p.src_addr || '?'} · ${formatBps(p.bps ?? null)}`
                                        )}
                                    />
                                </div>
                            </div>
                        ) : null}
                    </div>
                </div>
            )}

            {error && (
                <p css={tw`text-sm text-red-400 mt-3 mb-0`}>Não foi possível atualizar a lista de eventos.</p>
            )}
        </ServerContentBlock>
    );
};

const DetailList = ({ title, rows }: { title: string; rows: string[] }) => (
    <CardShell>
        <CardInner>
            <p css={[cardLabelText, tw`text-xs uppercase tracking-wide m-0 mb-2`]}>{title}</p>
            {rows.length === 0 ? (
                <p css={[cardValueText, tw`m-0 text-sm`]}>Sem dados</p>
            ) : (
                <ul css={tw`m-0 pl-4 space-y-1`}>
                    {rows.map((row) => (
                        <li key={row} css={[cardValueText, tw`text-sm`]}>
                            {row}
                        </li>
                    ))}
                </ul>
            )}
        </CardInner>
    </CardShell>
);
