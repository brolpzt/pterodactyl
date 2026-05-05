import React from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faChevronRight, faClock, faExclamationCircle, faCheckCircle, faLifeRing } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import GreyRowBox from '@/components/elements/GreyRowBox';
import { Link, useHistory } from 'react-router-dom';
import { useTickets } from '@/api/account/tickets';
import Spinner from '@/components/elements/Spinner';
import { formatDistanceToNow } from 'date-fns';
import styled from 'styled-components';
import { useTranslation } from 'react-i18next';

const Container = styled.div`
    ${tw`flex flex-col gap-3 shadow-none bg-transparent p-0`};
`;

const TicketRow = styled(GreyRowBox)`
    ${tw`flex items-center p-4 cursor-pointer transition-all duration-200 border border-neutral-700 hover:border-neutral-600 hover:bg-neutral-700 shadow-sm rounded-lg`};

    &:hover .ticket-chevron {
        ${tw`text-neutral-200`};
    }
`;

const StatusBadge = styled.span<{ $isOpen: boolean }>`
    ${tw`px-3 py-1 rounded-full text-[10px] uppercase font-black tracking-widest flex items-center shadow-sm border`};
    ${props => props.$isOpen ? tw`bg-neutral-600 text-neutral-100 border-neutral-500` : tw`bg-neutral-800 text-neutral-500 border-neutral-700`};
`;

export default () => {
    const history = useHistory();
    const { data: tickets, error } = useTickets();
    const { t } = useTranslation('strings');
    const translateStatus = (status: string) => (status === 'open' ? t('support.status_open') : t('support.status_closed'));
    const formatTicketId = (id: number) => `#${String(id).padStart(4, '0')}`;

    return (
        <PageContentBlock title={t('support.center_title')} showFlashKey={'support'}>
            <div css={tw`flex flex-col md:flex-row md:items-center justify-between mb-12 gap-6`}>
                <div>
                    <h1 css={tw`text-2xl font-black text-neutral-100`}>{t('support.center_title')}</h1>
                    <p css={tw`text-neutral-500 text-sm font-medium`}>{t('support.center_subtitle')}</p>
                </div>
                <Link to={'/account/support/new'}>
                    <Button color={'primary'}>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                        {t('support.create_new_ticket')}
                    </Button>
                </Link>
            </div>

            {!tickets && !error ? (
                <Spinner size={'large'} centered />
            ) : (
                <>
                    {tickets?.length === 0 ? (
                        <div css={tw`bg-neutral-800 border-2 border-dashed border-neutral-700 rounded-2xl p-20 flex flex-col items-center justify-center text-center`}>
                            <FontAwesomeIcon icon={faLifeRing} size={'3x'} css={tw`text-neutral-700 mb-6`} />
                            <h2 css={tw`text-xl font-bold text-neutral-400 mb-2`}>{t('support.no_tickets_title')}</h2>
                            <p css={tw`text-sm text-neutral-500 max-w-xs mb-8`}>{t('support.no_tickets_description')}</p>
                            <Link to={'/account/support/new'}>
                                <Button isSecondary>{t('support.start_conversation')}</Button>
                            </Link>
                        </div>
                    ) : (
                        <Container>
                            {tickets?.map((ticket) => (
                                <TicketRow
                                    key={ticket.id}
                                    onClick={() => history.push(`/account/support/${ticket.id}`)}
                                >
                                    <div css={tw`h-10 w-10 flex items-center justify-center rounded-lg bg-neutral-900 mr-4 text-neutral-500 shadow-inner`}>
                                        <FontAwesomeIcon icon={ticket.status === 'open' ? faExclamationCircle : faCheckCircle} />
                                    </div>
                                    <div css={tw`flex-1 min-w-0`}>
                                        <h3 css={tw`text-base font-bold text-neutral-100 mb-1 truncate`}>{ticket.subject}</h3>
                                        <p css={tw`text-[11px] text-neutral-500 font-bold uppercase tracking-widest`}>
                                            ID {formatTicketId(ticket.id)} &bull; <span css={tw`text-neutral-400`}>{ticket.department}</span> {ticket.serverName ? <>&bull; <span css={tw`text-neutral-500`}>{ticket.serverName}</span></> : ''}
                                        </p>
                                    </div>
                                    <div css={tw`flex items-center gap-6 ml-4`}>
                                        <div css={tw`hidden sm:flex flex-col items-end`}>
                                            <span css={tw`text-[10px] text-neutral-500 font-black uppercase tracking-widest mb-1`}>{t('support.last_activity')}</span>
                                            <p css={tw`text-xs text-neutral-300 font-bold`}>
                                                <FontAwesomeIcon icon={faClock} css={tw`mr-1.5 text-neutral-600`} />
                                                {formatDistanceToNow(ticket.updatedAt, { addSuffix: true })}
                                            </p>
                                        </div>
                                        <StatusBadge $isOpen={ticket.status === 'open'}>
                                            {translateStatus(ticket.status)}
                                        </StatusBadge>
                                        <div className={'ticket-chevron'} css={tw`text-neutral-600 transition-colors`}>
                                            <FontAwesomeIcon icon={faChevronRight} />
                                        </div>
                                    </div>
                                </TicketRow>
                            ))}
                        </Container>
                    )}
                </>
            )}
        </PageContentBlock>
    );
};
