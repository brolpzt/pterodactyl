import React from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faLifeRing, faChevronRight, faClock, faExclamationCircle, faCheckCircle } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import GreyRowBox from '@/components/elements/GreyRowBox';
import { Link, useHistory } from 'react-router-dom';
import { useTickets } from '@/api/account/tickets';
import Spinner from '@/components/elements/Spinner';
import { formatDistanceToNow } from 'date-fns';
import styled from 'styled-components/macro';

const Container = styled.div`
    ${tw`flex flex-col gap-3 shadow-none bg-transparent p-0`};
`;

const TicketRow = styled(GreyRowBox)`
    ${tw`flex items-center p-4 cursor-pointer transition-all duration-200 border border-neutral-700 hover:border-cyan-500 hover:bg-neutral-700 shadow-sm rounded-lg`};

    &:hover .ticket-chevron {
        ${tw`text-cyan-400`};
    }
`;

const StatusBadge = styled.span<{ $isOpen: boolean }>`
    ${tw`px-3 py-1 rounded-full text-[10px] uppercase font-black tracking-widest flex items-center shadow-sm`};
    ${props => props.$isOpen ? tw`bg-cyan-500 text-cyan-50 border border-cyan-500` : tw`bg-neutral-600 text-neutral-400 border border-neutral-700`};
`;

export default () => {
    const history = useHistory();
    const { data: tickets, error } = useTickets();

    return (
        <PageContentBlock title={'Support Center'} showFlashKey={'support'}>
            <div css={tw`flex flex-col md:flex-row md:items-center justify-between mb-12 gap-6`}>
                <div css={tw`flex items-center`}>
                    <div css={tw`h-12 w-12 bg-cyan-500 rounded-xl flex items-center justify-center mr-4 shadow-lg`}>
                        <FontAwesomeIcon icon={faLifeRing} css={tw`text-white text-xl`} />
                    </div>
                    <div>
                        <h1 css={tw`text-3xl font-black text-neutral-100`}>Support Center</h1>
                        <p css={tw`text-neutral-500 text-sm font-medium`}>Track your current issues and requests.</p>
                    </div>
                </div>
                <Link to={'/account/support/new'}>
                    <Button color={'primary'} css={tw`shadow-xl px-6 py-3 font-bold border-none transition-transform hover:scale-105 active:scale-95`}>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                        Create New Ticket
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
                            <h2 css={tw`text-xl font-bold text-neutral-400 mb-2`}>No active tickets found</h2>
                            <p css={tw`text-sm text-neutral-500 max-w-xs mb-8`}>If you're having trouble, don't hesitate to reach out to our professional support team.</p>
                            <Link to={'/account/support/new'}>
                                <Button isSecondary>Start a Conversation</Button>
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
                                        <FontAwesomeIcon icon={ticket.status === 'open' ? faExclamationCircle : faCheckCircle} css={ticket.status === 'open' ? tw`text-cyan-500` : tw`text-neutral-600`} />
                                    </div>
                                    <div css={tw`flex-1 min-w-0`}>
                                        <h3 css={tw`text-base font-bold text-neutral-100 mb-1 truncate`}>{ticket.subject}</h3>
                                        <p css={tw`text-[11px] text-neutral-500 font-bold uppercase tracking-widest`}>
                                            ID #{ticket.id} &bull; <span css={tw`text-neutral-400`}>{ticket.department}</span> {ticket.serverName ? <>&bull; <span css={tw`text-cyan-600`}>{ticket.serverName}</span></> : ''}
                                        </p>
                                    </div>
                                    <div css={tw`flex items-center gap-6 ml-4`}>
                                        <div css={tw`hidden sm:flex flex-col items-end`}>
                                            <span css={tw`text-[10px] text-neutral-500 font-black uppercase tracking-widest mb-1`}>Last Activity</span>
                                            <p css={tw`text-xs text-neutral-300 font-bold`}>
                                                <FontAwesomeIcon icon={faClock} css={tw`mr-1.5 text-neutral-600`} />
                                                {formatDistanceToNow(ticket.updatedAt, { addSuffix: true })}
                                            </p>
                                        </div>
                                        <StatusBadge $isOpen={ticket.status === 'open'}>
                                            {ticket.status}
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
