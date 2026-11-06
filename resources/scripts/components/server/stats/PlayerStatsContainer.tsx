import React, { useMemo } from 'react';
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
import { format, parseISO } from 'date-fns';
import tw from 'twin.macro';
import { theme } from 'twin.macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import ChartBlock from '@/components/server/console/ChartBlock';
import usePlayerStats from '@/api/swr/getPlayerStats';
import { ServerContext } from '@/state/server';
import { supportsGameQuery } from '@/lib/supportsGameQuery';
import { hexToRgba } from '@/lib/helpers';
import { cardLabelText, cardSurface, cardValueText } from '@/assets/css/cardTheme';
import { glassCardShell, glassContentLayer } from '@/assets/css/glassPanel';
import styled from 'styled-components/macro';
import { hgRadiusSm } from '@/assets/css/borderTheme';
import type { ChartOptions } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip);

const StatCardShell = styled.div.attrs({ className: 'hg-glass-card' })`
    ${glassCardShell};
    ${hgRadiusSm};
`;

const StatCardInner = styled.div`
    ${glassContentLayer};
    ${cardSurface};
    ${tw`relative z-10 p-4`};
`;

const formatHourLabel = (hour: number | null | undefined): string => {
    if (hour === null || hour === undefined) {
        return '—';
    }

    return `${String(hour).padStart(2, '0')}:00`;
};

const formatNumber = (value: number | null | undefined, digits = 0): string => {
    if (value === null || value === undefined) {
        return '—';
    }

    return digits > 0 ? value.toFixed(digits) : String(Math.round(value));
};

const SummaryCard = ({ label, value }: { label: string; value: string }) => (
    <StatCardShell>
        <StatCardInner>
            <p css={[cardLabelText, tw`text-xs uppercase tracking-wide m-0 mb-1`]}>{label}</p>
            <p css={[cardValueText, tw`text-2xl font-medium m-0`]}>{value}</p>
        </StatCardInner>
    </StatCardShell>
);

export default () => {
    const gamedig = ServerContext.useStoreState((state) => state.server.data?.gamedig);
    const eggId = ServerContext.useStoreState((state) => state.server.data?.eggId);
    const querySupported = supportsGameQuery(gamedig, eggId);

    const { data, error, isValidating } = usePlayerStats(true, 7);

    const chart = useMemo(() => {
        const samples = data?.samples ?? [];
        const labels = samples.map((sample) => format(parseISO(sample.sampled_at), 'dd/MM HH:mm'));
        const players = samples.map((sample) => sample.players);
        const suggestedMax = Math.max(1, ...(samples.map((sample) => sample.max_players || sample.players)), 1);

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
                        precision: 0,
                        color: theme('colors.gray.200'),
                        font: {
                            family: theme('fontFamily.sans'),
                            size: 11,
                        },
                    },
                },
            },
            elements: {
                point: { radius: 0 },
                line: { tension: 0.2 },
            },
        };

        return {
            data: {
                labels,
                datasets: [
                    {
                        label: 'Jogadores',
                        fill: true,
                        data: players,
                        borderColor: theme('colors.primary.400'),
                        backgroundColor: hexToRgba(theme('colors.primary.700'), 0.45),
                        pointRadius: 0,
                        tension: 0.2,
                    },
                ],
            },
            options,
        };
    }, [data?.samples]);

    if ((!data && !error) || (error && isValidating && !data)) {
        return (
            <ServerContentBlock title={'Stats'}>
                <Spinner size={'large'} centered />
            </ServerContentBlock>
        );
    }

    const supported = data?.supported ?? querySupported;
    const summary = data?.summary;
    const hasSamples = (data?.samples?.length ?? 0) > 0;

    return (
        <ServerContentBlock title={'Stats'}>
            <div css={tw`mb-4`}>
                <h2 css={tw`text-xl font-header font-medium text-gray-100 m-0`}>Jogadores online</h2>
                <p css={[cardLabelText, tw`text-sm mt-1 mb-0`]}>
                    Histórico dos últimos 7 dias, com amostragem a cada 5 minutos.
                </p>
            </div>

            {!supported ? (
                <StatCardShell>
                    <StatCardInner>
                        <p css={[cardValueText, tw`m-0`]}>
                            Este servidor não tem GameDig configurado. Sem isso não é possível consultar jogadores
                            online.
                        </p>
                    </StatCardInner>
                </StatCardShell>
            ) : (
                <>
                    <div css={tw`grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4`}>
                        <SummaryCard label={'Atual'} value={formatNumber(summary?.current)} />
                        <SummaryCard label={'Pico (7 dias)'} value={formatNumber(summary?.peak)} />
                        <SummaryCard label={'Média (7 dias)'} value={formatNumber(summary?.average, 1)} />
                        <SummaryCard
                            label={'Hora com mais jogadores'}
                            value={
                                summary?.busiest_hour
                                    ? `${formatHourLabel(summary.busiest_hour.hour)} (~${formatNumber(
                                          summary.busiest_hour.average_players,
                                          1
                                      )})`
                                    : '—'
                            }
                        />
                    </div>

                    {!hasSamples ? (
                        <StatCardShell>
                            <StatCardInner>
                                <p css={[cardValueText, tw`m-0`]}>
                                    Ainda não há amostras. A amostragem começa em breve e o gráfico aparece depois da
                                    primeira coleta.
                                </p>
                            </StatCardInner>
                        </StatCardShell>
                    ) : (
                        <ChartBlock title={'Jogadores conectados'}>
                            <div css={tw`h-64`}>
                                <Line data={chart.data} options={chart.options} />
                            </div>
                        </ChartBlock>
                    )}

                    {error && (
                        <p css={tw`text-sm text-red-400 mt-3 mb-0`}>
                            Não foi possível atualizar as estatísticas. A mostrar os dados em cache, se existirem.
                        </p>
                    )}
                </>
            )}
        </ServerContentBlock>
    );
};
