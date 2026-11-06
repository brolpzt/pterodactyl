import React, { useState } from 'react';
import useSWR from 'swr';
import { format, parseISO } from 'date-fns';
import tw from 'twin.macro';
import { faShieldVirus } from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Button from '@/components/elements/Button';
import PeriodSelector, { PeriodOption, PeriodValue } from '@/components/elements/PeriodSelector';
import { ServerContext } from '@/state/server';
import { useStoreState } from 'easy-peasy';
import {
    AntiDdosEventSummary,
    getAntiDdosEvent,
    getAntiDdosEvents,
} from '@/api/server/antiDdos/getAntiDdosEvents';
import AntiDdosEventModal from '@/components/server/antiDdos/AntiDdosEventModal';
import { cardLabelText, cardRowBorder, cardValueText, emptyStateText } from '@/assets/css/cardTheme';

const PERIOD_OPTIONS: PeriodOption[] = [
    { value: '15m', label: '15 min' },
    { value: '1h', label: '1 h' },
    { value: '6h', label: '6 h' },
    { value: '24h', label: '24 h' },
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
];

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
            <AntiDdosEventModal
                visible={selectedId !== null}
                loading={detailLoading}
                error={Boolean(detailError)}
                detail={detail}
                startedLabel={formatWhen(detail?.attack_start_time)}
                endedLabel={formatWhen(detail?.attack_end_time)}
                onDismissed={() => setSelectedId(null)}
            />

            <div css={tw`mb-4 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3`}>
                <div>
                    <h2 css={tw`text-xl font-header font-medium text-neutral-100 m-0`}>Anti-DDoS (beta)</h2>
                    <p css={[cardLabelText, tw`text-sm mt-1 mb-0`]}>
                        Eventos filtrados pelas portas deste servidor
                        {endpointsLabel ? `: ${endpointsLabel}` : ''}.
                    </p>
                </div>
                <PeriodSelector value={period} options={PERIOD_OPTIONS} onChange={onPeriodChange} />
            </div>

            {!data?.supported ? (
                <TitledGreyBox title={'Proteção Gcore'} icon={faShieldVirus}>
                    <p css={[cardValueText, tw`m-0`]}>
                        Este servidor não tem IP Gcore protegido associado. Marca o IP em Node → Gcore IPs.
                    </p>
                </TitledGreyBox>
            ) : (
                <TitledGreyBox title={'Eventos'} icon={faShieldVirus}>
                    {(data.events || []).length === 0 ? (
                        <p css={[emptyStateText, tw`py-4 m-0`]}>
                            Sem eventos neste período nas portas deste servidor. O sync corre a cada 5 minutos.
                        </p>
                    ) : (
                        <div css={tw`overflow-x-auto -mx-1`}>
                            <table css={tw`w-full text-sm`}>
                                <thead>
                                    <tr css={[tw`border-b`, cardRowBorder]}>
                                        <th css={[cardLabelText, tw`text-left py-2 font-medium`]}>Início</th>
                                        <th css={[cardLabelText, tw`text-left py-2 font-medium hidden md:table-cell`]}>
                                            Alvo
                                        </th>
                                        <th css={[cardLabelText, tw`text-left py-2 font-medium`]}>Estado</th>
                                        <th css={[cardLabelText, tw`text-right py-2 font-medium`]}>Potência</th>
                                        <th css={[cardLabelText, tw`text-right py-2 font-medium hidden sm:table-cell`]}>
                                            Pacotes
                                        </th>
                                        <th css={[cardLabelText, tw`text-right py-2 font-medium hidden lg:table-cell`]}>
                                            Parte
                                        </th>
                                        <th css={[cardLabelText, tw`text-right py-2 font-medium`]}>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(data.events || []).map((event: AntiDdosEventSummary) => {
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
                                                onClick={() => setSelectedId(event.id)}
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
                                                        {event.ongoing ? 'Em curso' : 'Terminado'}
                                                    </span>
                                                </td>
                                                <td css={[cardValueText, tw`py-3 pr-3 text-right whitespace-nowrap`]}>
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
                                                <td css={tw`py-3 text-right`} onClick={(e) => e.stopPropagation()}>
                                                    <Button
                                                        isSecondary
                                                        size={'xsmall'}
                                                        onClick={() => setSelectedId(event.id)}
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
                    )}
                </TitledGreyBox>
            )}

            {error && (
                <p css={tw`text-sm text-red-400 mt-3 mb-0`}>Não foi possível atualizar a lista de eventos.</p>
            )}
        </ServerContentBlock>
    );
};
