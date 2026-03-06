import React, { useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPaperPlane, faArrowLeft, faUserTie, faUser, faClock, faServer, faUndo } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import ContentBox from '@/components/elements/ContentBox';
import { Link, useParams } from 'react-router-dom';
import { useTicket, replyTicket, updateTicketStatus } from '@/api/account/tickets';
import useFlash from '@/plugins/useFlash';
import Spinner from '@/components/elements/Spinner';
import { formatDistanceToNow, format } from 'date-fns';

export default () => {
    const { id } = useParams<{ id: string }>();
    const ticketId = parseInt(id);
    const { addFlash, clearFlashes } = useFlash();
    const { data: ticket, error, mutate } = useTicket(ticketId);

    const [reply, setReply] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmitReply = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes('support');
        setIsSubmitting(true);

        replyTicket(ticketId, reply)
            .then(() => {
                setReply('');
                mutate();
                addFlash({ type: 'success', title: 'Success', message: 'Your reply has been added.', key: 'support' });
                setIsSubmitting(false);
            })
            .catch((error) => {
                setIsSubmitting(false);
                addFlash({ type: 'error', title: 'Error', message: error.response?.data?.errors[0]?.detail || 'An error occurred while replying.', key: 'support' });
            });
    };

    const toggleStatus = () => {
        if (!ticket) return;
        clearFlashes('support');
        const newStatus = ticket.status === 'open' ? 'closed' : 'open';

        updateTicketStatus(ticketId, newStatus)
            .then(() => {
                mutate();
                addFlash({ type: 'success', title: 'Success', message: `Ticket has been ${newStatus}.`, key: 'support' });
            })
            .catch((error) => {
                addFlash({ type: 'error', title: 'Error', message: error.response?.data?.errors[0]?.detail || 'An error occurred.', key: 'support' });
            });
    };

    if (error) {
        return (
            <PageContentBlock title={'Error'}>
                <div css={tw`bg-neutral-800 border-2 border-red-500/20 rounded-xl p-10 text-center shadow-2xl`}>
                    <p css={tw`text-red-400 font-bold text-lg mb-4`}>Failed to load ticket.</p>
                    <Link to={'/account/support'}>
                        <Button color={'grey'} isSecondary>Return to list</Button>
                    </Link>
                </div>
            </PageContentBlock>
        );
    }

    if (!ticket) {
        return (
            <PageContentBlock title={'Loading...'}>
                <div css={tw`flex justify-center py-20`}>
                    <Spinner size={'large'} />
                </div>
            </PageContentBlock>
        );
    }

    return (
        <PageContentBlock title={`Viewing Ticket #${ticket.id}`}>
            <div css={tw`flex flex-col lg:flex-row justify-between items-start lg:items-center mb-10 gap-4`}>
                <div css={tw`flex items-start lg:items-center`}>
                    <Link to={'/account/support'}>
                        <Button color={'grey'} isSecondary css={tw`mr-6 px-4 py-3 bg-neutral-800 shadow-md`}>
                            <FontAwesomeIcon icon={faArrowLeft} />
                        </Button>
                    </Link>
                    <div>
                        <h1 css={tw`text-3xl font-black text-neutral-100 tracking-tight`}>
                            {ticket.subject}
                        </h1>
                        <div css={tw`text-xs text-neutral-400 mt-2 flex flex-wrap items-center gap-2`}>
                            <span css={tw`bg-neutral-800 border border-neutral-700 px-2 py-0.5 rounded font-mono text-cyan-400 font-bold`}>#{ticket.id}</span>
                            <span css={[
                                tw`px-3 py-0.5 rounded-full text-[10px] uppercase font-black tracking-widest border`,
                                ticket.status === 'open' ? tw`bg-yellow-500/10 text-yellow-500 border-yellow-500/20` : tw`bg-green-500/10 text-green-500 border-green-500/20`
                            ]}>
                                {ticket.status === 'open' ? 'Open' : 'Closed'}
                            </span>
                            <span css={tw`text-neutral-500`}>•</span>
                            <span css={tw`flex items-center`}><FontAwesomeIcon icon={faClock} css={tw`mr-1.5`} /> {formatDistanceToNow(ticket.updatedAt, { addSuffix: true })}</span>
                            {ticket.serverName && (
                                <>
                                    <span css={tw`text-neutral-500`}>•</span>
                                    <span css={tw`flex items-center text-cyan-500/80 font-medium`}><FontAwesomeIcon icon={faServer} css={tw`mr-1.5`} /> {ticket.serverName}</span>
                                </>
                            )}
                        </div>
                    </div>
                </div>
                <Button
                    color={ticket.status === 'open' ? 'red' : 'green'}
                    isSecondary
                    onClick={toggleStatus}
                    css={tw`shadow-lg`}
                >
                    <FontAwesomeIcon icon={ticket.status === 'open' ? faUser : faUndo} css={tw`mr-2`} />
                    {ticket.status === 'open' ? 'Close Ticket' : 'Re-open Ticket'}
                </Button>
            </div>

            <div css={tw`grid grid-cols-1 lg:grid-cols-3 gap-8`}>
                <div css={tw`lg:col-span-2 space-y-6`}>
                    <div css={tw`space-y-4`}>
                        {ticket.messages?.map(msg => (
                            <div key={msg.id} css={[
                                tw`rounded-2xl p-6 shadow-sm border transition-shadow hover:shadow-md`,
                                msg.isStaff ? tw`bg-cyan-900/5 border-cyan-800/20 md:ml-6` : tw`bg-neutral-800 border-neutral-700`
                            ]}>
                                <div css={tw`flex items-center justify-between border-b border-neutral-700/50 pb-4 mb-4`}>
                                    <div css={tw`flex items-center`}>
                                        <div css={[
                                            tw`w-10 h-10 rounded-xl flex items-center justify-center mr-4 text-white shadow-inner`,
                                            msg.isStaff ? tw`bg-cyan-600` : tw`bg-neutral-600`
                                        ]}>
                                            <FontAwesomeIcon icon={msg.isStaff ? faUserTie : faUser} />
                                        </div>
                                        <div>
                                            <span css={tw`font-bold text-base text-neutral-100 block`}>
                                                {msg.userName}
                                            </span>
                                            {msg.isStaff && (
                                                <span css={tw`px-2 py-0.5 bg-cyan-600/20 text-cyan-400 text-[9px] rounded uppercase font-black tracking-widest border border-cyan-500/20`}>Company Staff</span>
                                            )}
                                        </div>
                                    </div>
                                    <span css={tw`text-[11px] text-neutral-500`}>
                                        {formatDistanceToNow(msg.createdAt, { addSuffix: true })}
                                    </span>
                                </div>
                                <div css={tw`text-neutral-300 text-sm whitespace-pre-wrap leading-relaxed px-1`}>
                                    {msg.message}
                                </div>
                            </div>
                        ))}
                    </div>

                    {ticket.status === 'open' && (
                        <div css={tw`mt-10 bg-neutral-800/30 border border-neutral-700/50 rounded-2xl p-1 shadow-inner`}>
                            <form onSubmit={handleSubmitReply} css={tw`p-6`}>
                                <label css={tw`block text-sm font-bold text-neutral-200 mb-4`}>Post a Reply</label>
                                <textarea
                                    css={tw`p-4 w-full border border-neutral-700 bg-neutral-900 rounded-xl shadow-inner text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all h-40 resize-y text-neutral-200`}
                                    placeholder={'Type your message to the support team...'}
                                    value={reply}
                                    onChange={(e) => setReply(e.target.value)}
                                    required
                                />
                                <div css={tw`flex justify-end mt-4`}>
                                    <Button type={'submit'} color={'primary'} disabled={isSubmitting || !reply.trim()} css={tw`px-6 py-2.5 shadow-lg`}>
                                        <FontAwesomeIcon icon={faPaperPlane} css={tw`mr-2`} />
                                        Send Message
                                    </Button>
                                </div>
                            </form>
                        </div>
                    )}
                </div>

                <div css={tw`lg:col-span-1 space-y-6`}>
                    <ContentBox title={'Ticket Details'} css={tw`shadow-lg border-neutral-700/50`}>
                        <div css={tw`space-y-6 text-sm`}>
                            <div>
                                <span css={tw`block text-neutral-500 font-bold uppercase tracking-wider text-[10px] mb-2`}>Department</span>
                                <span css={tw`text-neutral-200 bg-neutral-800 px-3 py-1.5 rounded-lg border border-neutral-700 inline-block w-full`}>{ticket.department}</span>
                            </div>
                            {ticket.serverName && (
                                <div>
                                    <span css={tw`block text-neutral-500 font-bold uppercase tracking-wider text-[10px] mb-2`}>Related Server</span>
                                    <span css={tw`text-cyan-400 bg-cyan-900/10 px-3 py-1.5 rounded-lg border border-cyan-800/20 inline-block w-full cursor-pointer hover:bg-cyan-900/20 transition-colors`}>{ticket.serverName}</span>
                                </div>
                            )}
                            <div>
                                <span css={tw`block text-neutral-500 font-bold uppercase tracking-wider text-[10px] mb-2`}>Current Status</span>
                                <div css={[
                                    tw`px-3 py-1.5 rounded-lg border inline-block w-full text-center font-bold`,
                                    ticket.status === 'open' ? tw`bg-yellow-500/10 text-yellow-500 border-yellow-500/20` : tw`bg-green-500/10 text-green-500 border-green-500/20`
                                ]}>
                                    {ticket.status === 'open' ? 'Awaiting Interaction' : 'Solved / Closed'}
                                </div>
                            </div>
                            <div css={tw`pt-4 border-t border-neutral-700/50 flex justify-between items-center`}>
                                <div>
                                    <span css={tw`block text-neutral-500 text-[10px] uppercase font-bold`}>Created</span>
                                    <span css={tw`text-neutral-400`}>{format(ticket.createdAt, 'MMM dd, yyyy')}</span>
                                </div>
                                <div css={tw`text-right`}>
                                    <span css={tw`block text-neutral-500 text-[10px] uppercase font-bold`}>Time</span>
                                    <span css={tw`text-neutral-400`}>{format(ticket.createdAt, 'HH:mm')}</span>
                                </div>
                            </div>
                        </div>
                    </ContentBox>

                    <div css={tw`bg-cyan-900/10 border border-cyan-800/20 rounded-xl p-5 shadow-sm`}>
                        <h5 css={tw`text-cyan-400 font-bold text-xs uppercase mb-2 tracking-widest`}>Staff Note</h5>
                        <p css={tw`text-neutral-400 text-[11px] italic`}>Please allow up to 24 hours for a response during business days.</p>
                    </div>
                </div>
            </div>
        </PageContentBlock>
    );
};
