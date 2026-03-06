import React from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faCheckCircle, faClock } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import GreyRowBox from '@/components/elements/GreyRowBox';
import { Link, useHistory } from 'react-router-dom';
import { useTickets } from '@/api/account/tickets';
import Spinner from '@/components/elements/Spinner';
import { formatDistanceToNow } from 'date-fns';

export default () => {
    const history = useHistory();
    const { data: tickets, error } = useTickets();

    return (
        <PageContentBlock title={'Support Tickets'}>
            <div css={tw`flex justify-between items-center mb-6`}>
                <h1 css={tw`text-2xl font-bold flex items-center`}>
                    Support Center
                </h1>
                <Link to={'/account/support/new'}>
                    <Button color={'primary'}>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                        Create New Ticket
                    </Button>
                </Link>
            </div>

            {!tickets ? (
                <div css={tw`w-full flex justify-center py-6`}>
                    <Spinner size={'large'} />
                </div>
            ) : error ? (
                <p css={tw`text-center text-sm text-red-400 mt-4`}>Failed to load tickets. Please try again later.</p>
            ) : !tickets.length ? (
                <p css={tw`text-center text-sm text-neutral-400 mt-4`}>You don't have any support tickets yet.</p>
            ) : (
                <div css={tw`mt-4`}>
                    {tickets.map((ticket, index) => (
                        <GreyRowBox
                            key={ticket.id}
                            className={'group'}
                            css={[tw`flex-wrap md:flex-nowrap items-center cursor-pointer`, index > 0 && tw`mt-2`]}
                            onClick={() => history.push(`/account/support/${ticket.id}`)}
                        >
                            <div css={tw`flex items-center justify-center w-10 h-10 rounded-full bg-neutral-600 group-hover:bg-cyan-600 transition-colors mr-4 flex-shrink-0`}>
                                <FontAwesomeIcon icon={ticket.status === 'open' ? faClock : faCheckCircle} css={tw`text-neutral-300 group-hover:text-white`} />
                            </div>
                            <div css={tw`flex-1 mr-4`}>
                                <p css={tw`text-lg text-neutral-200 group-hover:text-cyan-400 transition-colors break-words`}>
                                    {ticket.subject}
                                </p>
                                <p css={tw`text-xs text-neutral-400 uppercase font-bold mt-1 tracking-wider`}>
                                    <span css={tw`text-neutral-300`}>#{ticket.id}</span> • {ticket.department}
                                    {ticket.serverName && <span css={tw`ml-2 border-l border-neutral-600 pl-2`}>{ticket.serverName}</span>}
                                </p>
                            </div>
                            <div css={tw`flex-shrink-0 w-full md:w-auto mt-4 md:mt-0 flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center`}>
                                <span
                                    css={[
                                        tw`px-3 py-1 rounded-full text-xs font-bold w-max uppercase tracking-widest`,
                                        ticket.status === 'open' ? tw`bg-yellow-500/10 text-yellow-500 border border-yellow-500/20` : tw`bg-gray-500/10 text-gray-400 border border-gray-500/20`
                                    ]}
                                >
                                    {ticket.status === 'open' ? 'Open' : 'Closed'}
                                </span>
                                <p css={tw`text-sm text-neutral-400 md:mt-2`}>
                                    Last update: <strong css={tw`text-neutral-300`}>{formatDistanceToNow(ticket.updatedAt, { addSuffix: true })}</strong>
                                </p>
                            </div>
                        </GreyRowBox>
                    ))}
                </div>
            )}
        </PageContentBlock>
    );
};
