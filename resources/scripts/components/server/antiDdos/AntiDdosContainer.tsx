import React, { useState } from 'react';
import useSWR from 'swr';
import { format, parseISO } from 'date-fns';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
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
                        Só eventos que atingiram as portas deste servidor
                        {data?.endpoints?.length
                            ? `: ${data.endpoints.map((e) => `${e.ip}:${e.port}`).join(', ')}`
                            : data?.ips?.length
                            ? ` (IP: ${data.ips.join(', ')})`
                            : ''}
                        .
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
                                        Sem eventos neste período nas portas deste servidor. O sync corre a cada 5
                                        minutos.
                                    </p>
                                </CardInner>
                            </CardShell>
                        ) : (
                            data.events.map((event: AntiDdosEventSummary) => {
                                const active = selectedId === event.id;
                                const ports = (event.ports || [])
                                    .map((port) => `${event.targeted_ip}:${port}`)
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
                                                    {event.share_percentage != null
                                                        ? ` · ${event.share_percentage}% do evento`
                                                        : ''}
                                                </p>
                                                {ports && (
                                                    <p css={[cardLabelText, tw`text-xs m-0 mt-1`]}>{ports}</p>
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
                                            <p css={[cardLabelText, tw`text-xs m-0 mb-1`]}>Potência (esta porta)</p>
                                            <p css={[cardValueText, tw`m-0`]}>{formatBps(detail.attack_power_bps)}</p>
                                        </CardInner>
                                    </CardShell>
                                    <CardShell>
                                        <CardInner>
                                            <p css={[cardLabelText, tw`text-xs m-0 mb-1`]}>Pacotes (esta porta)</p>
                                            <p css={[cardValueText, tw`m-0`]}>{formatPps(detail.attack_power_pps)}</p>
                                        </CardInner>
                                    </CardShell>
                                    <CardShell>
                                        <CardInner>
                                            <p css={[cardLabelText, tw`text-xs m-0 mb-1`]}>Parte do evento</p>
                                            <p css={[cardValueText, tw`m-0`]}>
                                                {detail.share_percentage != null ? `${detail.share_percentage}%` : '—'}
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

                                <CardShell>
                                    <CardInner>
                                        <p css={[cardLabelText, tw`text-xs m-0 mb-1`]}>Alvo deste servidor</p>
                                        <p css={[cardValueText, tw`m-0`]}>
                                            {(detail.ports || [])
                                                .map((port) => `${detail.targeted_ip}:${port}`)
                                                .join(', ') || `${detail.targeted_ip}`}
                                        </p>
                                    </CardInner>
                                </CardShell>

                                <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                                    <DetailList
                                        title={'Portas deste servidor'}
                                        rows={(detail.attack_top_destination_ports || []).map(
                                            (p) =>
                                                `${detail.targeted_ip}:${p.dst_port ?? '?'}/${p.protocol || '?'} · ${formatBps(p.bps ?? null)} (${p.bps_percentage ?? 0}%)`
                                        )}
                                    />
                                    <DetailList
                                        title={'Protocolos nestas portas'}
                                        rows={(detail.attack_top_protocols || []).map(
                                            (p) =>
                                                `${p.protocol || '?'} · ${formatBps(p.bps ?? null)} (${p.bps_percentage ?? 0}%)`
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
