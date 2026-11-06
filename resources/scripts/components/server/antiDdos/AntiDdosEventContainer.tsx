import React from 'react';
import useSWR from 'swr';
import tw from 'twin.macro';
import { faArrowLeft, faShieldVirus } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { Link, useHistory, useParams } from 'react-router-dom';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Button from '@/components/elements/Button';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { ServerContext } from '@/state/server';
import { getAntiDdosEvent } from '@/api/server/antiDdos/getAntiDdosEvents';
import { formatBps, formatPps, formatWhen } from '@/components/server/antiDdos/format';
import { cardLabelText, cardRowBorder, cardValueText } from '@/assets/css/cardTheme';

const ColumnLabel = ({ label, tip }: { label: string; tip: string }) => (
    <Tooltip placement={'top'} content={tip}>
        <span css={tw`cursor-help border-b border-dashed border-neutral-500`}>{label}</span>
    </Tooltip>
);

const InfoRow = ({ label, value, copyValue }: { label: string; value: React.ReactNode; copyValue?: string }) => (
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

export default () => {
    const history = useHistory();
    const { eventId } = useParams<{ eventId: string }>();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const listPath = `/server/${id}/anti-ddos`;

    const {
        data: detail,
        error,
        isValidating,
    } = useSWR(eventId ? ['server:anti-ddos-detail', uuid, eventId] : null, () => getAntiDdosEvent(uuid, eventId), {
        revalidateOnFocus: false,
    });

    if ((!detail && !error) || (error && isValidating && !detail)) {
        return (
            <ServerContentBlock title={'Anti-DDoS'}>
                <Spinner size={'large'} centered />
            </ServerContentBlock>
        );
    }

    const targets =
        detail?.ports?.map((port) => `${detail.targeted_ip}:${port}`).join(', ') || detail?.targeted_ip || '—';

    return (
        <ServerContentBlock title={'Anti-DDoS'}>
            <div css={tw`mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3`}>
                <div>
                    <button
                        type={'button'}
                        css={tw`inline-flex items-center text-sm text-neutral-300 hover:text-neutral-100 mb-2 bg-transparent border-0 p-0 cursor-pointer`}
                        onClick={() => history.push(listPath)}
                    >
                        <FontAwesomeIcon icon={faArrowLeft} css={tw`mr-2`} />
                        Voltar aos eventos
                    </button>
                    <h2 css={tw`text-xl font-header font-medium text-neutral-100 m-0`}>Detalhe do evento</h2>
                    <p css={[cardLabelText, tw`text-sm mt-1 mb-0`]}>
                        Dados filtrados apenas para as portas deste servidor.
                    </p>
                </div>
                <Button isSecondary onClick={() => history.push(listPath)}>
                    Voltar
                </Button>
            </div>

            {error || !detail ? (
                <TitledGreyBox title={'Evento'} icon={faShieldVirus}>
                    <p css={[cardValueText, tw`m-0`]}>Não foi possível carregar o detalhe deste evento.</p>
                    <p css={tw`mt-4 mb-0`}>
                        <Link to={listPath} css={tw`text-primary-300 hover:text-primary-200`}>
                            Voltar à lista
                        </Link>
                    </p>
                </TitledGreyBox>
            ) : (
                <>
                    <div css={[tw`grid gap-4 lg:grid-cols-2 items-start`, { '& > *': { minWidth: 0 } }]}>
                        <TitledGreyBox title={'Resumo'} icon={faShieldVirus}>
                            <InfoRow
                                label={'Estado'}
                                value={
                                    detail.ongoing ? (
                                        <span css={tw`text-red-300`}>Em andamento</span>
                                    ) : (
                                        <span css={tw`text-neutral-300`}>Terminado</span>
                                    )
                                }
                            />
                            <InfoRow label={'Início'} value={formatWhen(detail.attack_start_time)} />
                            <InfoRow label={'Fim'} value={formatWhen(detail.attack_end_time)} />
                            <InfoRow label={'Alvo'} value={targets} copyValue={targets !== '—' ? targets : undefined} />
                            <InfoRow label={'Potência (esta porta)'} value={formatBps(detail.attack_power_bps)} />
                            <InfoRow label={'Pacotes (esta porta)'} value={formatPps(detail.attack_power_pps)} />
                            <InfoRow
                                label={'% neste servidor'}
                                value={detail.share_percentage != null ? `${detail.share_percentage}%` : '—'}
                            />
                        </TitledGreyBox>

                        <TitledGreyBox title={'Portas deste servidor'} icon={faShieldVirus}>
                            {(detail.attack_top_destination_ports || []).length === 0 ? (
                                <p css={[cardValueText, tw`text-sm m-0`]}>Sem dados de porta.</p>
                            ) : (
                                <div css={tw`overflow-x-auto`}>
                                    <table css={tw`w-full text-sm`}>
                                        <thead>
                                            <tr css={[tw`border-b`, cardRowBorder]}>
                                                <th css={[cardLabelText, tw`text-left py-2 font-medium`]}>
                                                    <ColumnLabel
                                                        label={'Destino'}
                                                        tip={'IP e porta de destino neste servidor'}
                                                    />
                                                </th>
                                                <th css={[cardLabelText, tw`text-left py-2 font-medium`]}>
                                                    <ColumnLabel
                                                        label={'Protocolo'}
                                                        tip={'Protocolo de rede do tráfego (UDP, TCP, etc.)'}
                                                    />
                                                </th>
                                                <th css={[cardLabelText, tw`text-right py-2 font-medium`]}>
                                                    <ColumnLabel
                                                        label={'Potência'}
                                                        tip={'Volume do ataque nesta porta (bits por segundo)'}
                                                    />
                                                </th>
                                                <th css={[cardLabelText, tw`text-right py-2 font-medium`]}>
                                                    <ColumnLabel
                                                        label={'% do ataque'}
                                                        tip={'Porcentagem do tráfego do ataque que caiu nesta porta'}
                                                    />
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(detail.attack_top_destination_ports || []).map((row, index) => (
                                                <tr
                                                    key={`${row.dst_port}-${index}`}
                                                    css={[tw`border-b last:border-0`, cardRowBorder]}
                                                >
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
                        </TitledGreyBox>
                    </div>
                </>
            )}
        </ServerContentBlock>
    );
};
