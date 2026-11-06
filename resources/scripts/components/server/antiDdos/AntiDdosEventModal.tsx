import React from 'react';
import tw from 'twin.macro';
import Modal from '@/components/elements/Modal';
import Spinner from '@/components/elements/Spinner';
import Button from '@/components/elements/Button';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { AntiDdosEventDetail } from '@/api/server/antiDdos/getAntiDdosEvents';
import { cardLabelText, cardRowBorder, cardValueText } from '@/assets/css/cardTheme';

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

const InfoRow = ({
    label,
    value,
    copyValue,
}: {
    label: string;
    value: React.ReactNode;
    copyValue?: string;
}) => (
    <div css={[tw`flex items-center justify-between text-sm py-2 border-b last:border-0`, cardRowBorder]}>
        <p css={cardLabelText}>{label}</p>
        {copyValue !== undefined ? (
            <CopyOnClick text={copyValue}>
                <code css={[cardValueText, tw`font-mono rounded py-1 px-2 cursor-pointer`]}>{value}</code>
            </CopyOnClick>
        ) : (
            <span css={[cardValueText, tw`font-medium text-right`]}>{value}</span>
        )}
    </div>
);

interface Props {
    visible: boolean;
    loading: boolean;
    error: boolean;
    detail: AntiDdosEventDetail | undefined;
    startedLabel: string;
    endedLabel: string;
    onDismissed: () => void;
}

export default ({ visible, loading, error, detail, startedLabel, endedLabel, onDismissed }: Props) => {
    const targets =
        detail?.ports?.map((port) => `${detail.targeted_ip}:${port}`).join(', ') || detail?.targeted_ip || '—';

    return (
        <Modal visible={visible} onDismissed={onDismissed} top>
            <h3 css={tw`mb-1 text-2xl text-neutral-100`}>Detalhe do evento</h3>
            <p css={[cardLabelText, tw`text-sm mt-0 mb-5`]}>
                Dados filtrados apenas para as portas deste servidor.
            </p>

            {loading && !detail ? (
                <div css={tw`py-10`}>
                    <Spinner size={'large'} centered />
                </div>
            ) : error ? (
                <p css={tw`text-red-400 mb-6`}>Não foi possível carregar o detalhe deste evento.</p>
            ) : detail ? (
                <>
                    <div css={tw`mb-5`}>
                        <InfoRow
                            label={'Estado'}
                            value={
                                detail.ongoing ? (
                                    <span css={tw`text-red-300`}>Em curso</span>
                                ) : (
                                    <span css={tw`text-neutral-300`}>Terminado</span>
                                )
                            }
                        />
                        <InfoRow label={'Início'} value={startedLabel} />
                        <InfoRow label={'Fim'} value={endedLabel} />
                        <InfoRow label={'Alvo'} value={targets} copyValue={targets !== '—' ? targets : undefined} />
                        <InfoRow label={'Potência (esta porta)'} value={formatBps(detail.attack_power_bps)} />
                        <InfoRow label={'Pacotes (esta porta)'} value={formatPps(detail.attack_power_pps)} />
                        <InfoRow
                            label={'Parte do evento'}
                            value={detail.share_percentage != null ? `${detail.share_percentage}%` : '—'}
                        />
                    </div>

                    <div css={tw`mb-5`}>
                        <p css={[cardLabelText, tw`text-xs uppercase tracking-wide m-0 mb-2`]}>
                            Portas deste servidor
                        </p>
                        {(detail.attack_top_destination_ports || []).length === 0 ? (
                            <p css={[cardValueText, tw`text-sm m-0`]}>Sem dados de porta.</p>
                        ) : (
                            <div css={tw`overflow-x-auto`}>
                                <table css={tw`w-full text-sm`}>
                                    <thead>
                                        <tr css={[tw`border-b`, cardRowBorder]}>
                                            <th css={[cardLabelText, tw`text-left py-2 font-medium`]}>Destino</th>
                                            <th css={[cardLabelText, tw`text-left py-2 font-medium`]}>Protocolo</th>
                                            <th css={[cardLabelText, tw`text-right py-2 font-medium`]}>Potência</th>
                                            <th css={[cardLabelText, tw`text-right py-2 font-medium`]}>Parte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(detail.attack_top_destination_ports || []).map((row, index) => (
                                            <tr key={`${row.dst_port}-${index}`} css={[tw`border-b last:border-0`, cardRowBorder]}>
                                                <td css={[cardValueText, tw`py-2 font-mono text-xs`]}>
                                                    {detail.targeted_ip}:{row.dst_port ?? '?'}
                                                </td>
                                                <td css={[cardValueText, tw`py-2`]}>{row.protocol || '—'}</td>
                                                <td css={[cardValueText, tw`py-2 text-right`]}>
                                                    {formatBps(row.bps ?? null)}
                                                </td>
                                                <td css={[cardValueText, tw`py-2 text-right`]}>
                                                    {row.bps_percentage != null ? `${row.bps_percentage}%` : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    <div css={tw`mb-6`}>
                        <p css={[cardLabelText, tw`text-xs uppercase tracking-wide m-0 mb-2`]}>
                            Protocolos nestas portas
                        </p>
                        {(detail.attack_top_protocols || []).length === 0 ? (
                            <p css={[cardValueText, tw`text-sm m-0`]}>Sem dados de protocolo.</p>
                        ) : (
                            <div css={tw`overflow-x-auto`}>
                                <table css={tw`w-full text-sm`}>
                                    <thead>
                                        <tr css={[tw`border-b`, cardRowBorder]}>
                                            <th css={[cardLabelText, tw`text-left py-2 font-medium`]}>Protocolo</th>
                                            <th css={[cardLabelText, tw`text-right py-2 font-medium`]}>Potência</th>
                                            <th css={[cardLabelText, tw`text-right py-2 font-medium`]}>Parte</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(detail.attack_top_protocols || []).map((row, index) => (
                                            <tr key={`${row.protocol}-${index}`} css={[tw`border-b last:border-0`, cardRowBorder]}>
                                                <td css={[cardValueText, tw`py-2`]}>{row.protocol || '—'}</td>
                                                <td css={[cardValueText, tw`py-2 text-right`]}>
                                                    {formatBps(row.bps ?? null)}
                                                </td>
                                                <td css={[cardValueText, tw`py-2 text-right`]}>
                                                    {row.bps_percentage != null ? `${row.bps_percentage}%` : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </>
            ) : null}

            <div css={tw`text-right`}>
                <Button isSecondary onClick={onDismissed}>
                    Fechar
                </Button>
            </div>
        </Modal>
    );
};
