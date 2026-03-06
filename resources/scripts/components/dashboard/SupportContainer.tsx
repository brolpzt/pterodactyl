import React from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faCheckCircle, faClock, faInbox, faTicketAlt } from '@fortawesome/free-solid-svg-icons';
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
            <div css={tw`flex flex-col md:flex-row justify-between items-start md:items-center mb-10`}>
                <div>
                    <h1 css={tw`text-3xl font-black flex items-center text-neutral-100`}>
                        <FontAwesomeIcon icon={faTicketAlt} css={tw`mr-3 text-cyan-500`} />
                        Support Center
                    </h1>
                    <p css={tw`text-neutral-400 mt-2`}>Manage your support requests and get help from our staff.</p>
                </div>
                <Link to={'/account/support/new'} css={tw`mt-4 md:mt-0`}>
                    <Button color={'primary'} css={tw`flex items-center shadow-lg`}>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                        New Ticket
                    </Button>
                </Link>
            </div>

            {!tickets ? (
                <div css={tw`w-full flex justify-center py-20`}>
                    <Spinner size={'large'} />
                </div>
            ) : error ? (
                <div css={tw`bg-red-500/10 border border-red-500/20 rounded-lg p-6 text-center shadow-xl`}>
                    <p css={tw`text-red-400 font-medium`}>Failed to load tickets. Please try again later.</p>
                </div>
            ) : !tickets.length ? (
                <div css={tw`bg-neutral-800/50 border border-neutral-700 rounded-xl p-16 text-center flex flex-col items-center shadow-inner`}>
                    <div css={tw`w-20 h-20 bg-neutral-700/50 rounded-full flex items-center justify-center mb-6`}>
                        <FontAwesomeIcon icon={faInbox} size={'2x'} css={tw`text-neutral-500`} />
                    </div>
                    <h3 css={tw`text-xl font-bold text-neutral-200 mb-2`}>No tickets found</h3>
                    <p css={tw`text-neutral-400 mb-8 max-w-sm mx-auto`}>You don't have any support tickets yet. If you need assistance, click the button above to create one.</p>
                    <Link to={'/account/support/new'}>
                        <Button isSecondary>Create your first ticket</Button>
                    </Link>
                </div>
            ) : (
                <div css={tw`grid grid-cols-1 gap-3`}>
                    {tickets.map((ticket) => (
                        <GreyRowBox
                            key={ticket.id}
                            className={'group'}
                            css={[tw`flex-wrap md:flex-nowrap items-center cursor-pointer hover:border-cyan-500/50 transition-all duration-200 border border-transparent shadow-sm hover:shadow-md`]}
                            onClick={() => history.push(`/account/support/${ticket.id}`)}
                        >
                            <div css={tw`flex items-center justify-center w-12 h-12 rounded-xl bg-neutral-700/80 group-hover:bg-cyan-600/20 transition-all mr-5 flex-shrink-0 shadow-inner`}>
                                <FontAwesomeIcon
                                    icon={ticket.status === 'open' ? faClock : faCheckCircle}
                                    css={[
                                        tw`transition-colors`,
                                        ticket.status === 'open' ? tw`text-yellow-500 group-hover:text-yellow-400` : tw`text-green-500 group-hover:text-green-400`
                                    ]}
                                />
                            </div>
                            <div css={tw`flex-1 mr-4 py-1`}>
                                <h4 css={tw`text-lg font-semibold text-neutral-100 group-hover:text-cyan-400 transition-colors break-words`}>
                                    {ticket.subject}
                                </h4>
                                <div css={tw`flex items-center text-xs text-neutral-400 mt-1 space-x-2`}>
                                    <span css={tw`bg-neutral-800 px-2 py-0.5 rounded text-neutral-300 font-mono`}>#{ticket.id}</span>
                                    <span>•</span>
                                    <span css={tw`font-medium text-neutral-300 uppercase tracking-tighter`}>{ticket.department}</span>
                                    {ticket.serverName && (
                                        <>
                                            <span>•</span>
                                            <span css={tw`text-cyan-500/80 font-medium`}>{ticket.serverName}</span>
                                        </>
                                    )}
                                </div>
                            </div>
                            <div css={tw`flex-shrink-0 w-full md:w-auto mt-4 md:mt-0 flex flex-row md:flex-col items-center md:items-end justify-between md:justify-center`}>
                                <span
                                    css={[
                                        tw`px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest border`,
                                        ticket.status === 'open'
                                            ? tw`bg-yellow-500/10 text-yellow-500 border-yellow-500/20`
                                            : tw`bg-gray-500/10 text-gray-400 border-gray-500/20`
                                    ]}
                                >
                                    {ticket.status === 'open' ? 'Open' : 'Closed'}
                                </span>
                                <p css={tw`text-[11px] text-neutral-500 md:mt-3 flex items-center`}>
                                    <FontAwesomeIcon icon={faClock} css={tw`mr-1 text-[9px]`} />
                                    {formatDistanceToNow(ticket.updatedAt, { addSuffix: true })}
                                </p>
                            </div>
                        </GreyRowBox>
                    ))}
                </div>
            )}
        </PageContentBlock>
    );
};
