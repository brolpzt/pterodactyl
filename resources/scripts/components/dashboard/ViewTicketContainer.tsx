import React, { useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPaperPlane, faArrowLeft, faUserTie, faUser, faClock, faServer } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import ContentBox from '@/components/elements/ContentBox';
import { Link, useParams } from 'react-router-dom';
import { useTicket, replyTicket } from '@/api/account/tickets';
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
            .then((newMsg) => {
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

    if (error) {
        return (
            <PageContentBlock title={'Error'}>
                <p css={tw`text-center text-red-500 mt-6`}>Failed to load ticket.</p>
            </PageContentBlock>
        );
    }

    if (!ticket) {
        return (
            <PageContentBlock title={'Loading...'}>
                <div css={tw`flex justify-center py-10`}>
                    <Spinner size={'large'} />
                </div>
            </PageContentBlock>
        );
    }

    return (
        <PageContentBlock title={`Viewing Ticket #${ticket.id}`}>
            <div css={tw`flex justify-between items-center mb-6`}>
                <div css={tw`flex items-center`}>
                    <Link to={'/account/support'}>
                        <Button color={'grey'} isSecondary css={tw`mr-4 px-3`}>
                            <FontAwesomeIcon icon={faArrowLeft} />
                        </Button>
                    </Link>
                    <div>
                        <h1 css={tw`text-2xl font-bold flex items-center`}>
                            {ticket.subject}
                        </h1>
                        <p css={tw`text-sm text-neutral-400 mt-1 flex items-center`}>
                            <span css={tw`font-mono text-cyan-400 font-bold mr-2`}>#{ticket.id}</span>
                            • <span css={[tw`px-2 py-0.5 rounded text-xs ml-2 uppercase font-bold`, ticket.status === 'open' ? tw`bg-yellow-500/10 text-yellow-500 border border-yellow-500/20` : tw`bg-gray-500/10 text-gray-400 border border-gray-500/20`]}>{ticket.status === 'open' ? 'Open' : 'Closed'}</span>
                            • <FontAwesomeIcon icon={faClock} css={tw`mx-2 text-neutral-500`} /> {formatDistanceToNow(ticket.updatedAt, { addSuffix: true })}
                            {ticket.serverName && <>• <FontAwesomeIcon icon={faServer} css={tw`mx-2 text-neutral-500`} /> {ticket.serverName}</>}
                        </p>
                    </div>
                </div>
                <Button color={'red'} isSecondary>
                    Close Ticket
                </Button>
            </div>

            <div css={tw`grid grid-cols-1 lg:grid-cols-3 gap-8`}>
                <div css={tw`lg:col-span-2 space-y-4`}>
                    {ticket.messages?.map(msg => (
                        <div key={msg.id} css={[tw`rounded-lg p-5 shadow-sm border`, msg.isStaff ? tw`bg-cyan-900/10 border-cyan-800/30` : tw`bg-neutral-800 border-neutral-700`]}>
                            <div css={tw`flex items-center justify-between border-b border-neutral-700/50 pb-3 mb-3`}>
                                <div css={tw`flex items-center`}>
                                    <div css={[tw`w-8 h-8 rounded-full flex items-center justify-center mr-3 text-white`, msg.isStaff ? tw`bg-cyan-600` : tw`bg-neutral-600`]}>
                                        <FontAwesomeIcon icon={msg.isStaff ? faUserTie : faUser} size={'sm'} />
                                    </div>
                                    <div>
                                        <span css={tw`font-bold text-sm text-neutral-200`}>
                                            {msg.userName}
                                            {msg.isStaff && <span css={tw`ml-2 px-1.5 py-0.5 bg-cyan-600 text-white text-[10px] rounded uppercase font-bold tracking-wider`}>Staff</span>}
                                        </span>
                                    </div>
                                </div>
                                <span css={tw`text-xs text-neutral-500`}>{formatDistanceToNow(msg.createdAt, { addSuffix: true })}</span>
                            </div>
                            <div css={tw`text-neutral-300 text-sm whitespace-pre-wrap leading-relaxed`}>
                                {msg.message}
                            </div>
                        </div>
                    ))}

                    <div css={tw`mt-8 pt-4 border-t border-neutral-700`}>
                        <form onSubmit={handleSubmitReply}>
                            <label css={tw`block text-sm font-medium text-neutral-300 mb-2`}>Post a Reply</label>
                            <textarea
                                css={tw`p-3 w-full border border-neutral-700 bg-neutral-900 rounded-md shadow-inner text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-shadow h-32 resize-y`}
                                placeholder={'Type your reply here...'}
                                value={reply}
                                onChange={(e) => setReply(e.target.value)}
                                required
                            />
                            <div css={tw`flex justify-end mt-3`}>
                                <Button type={'submit'} color={'primary'} disabled={isSubmitting || !reply}>
                                    <FontAwesomeIcon icon={faPaperPlane} css={tw`mr-2`} />
                                    Send Reply
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                <div css={tw`lg:col-span-1`}>
                    <ContentBox title={'Ticket Details'}>
                        <div css={tw`space-y-4 text-sm`}>
                            <div>
                                <span css={tw`block text-neutral-500 font-medium mb-1`}>Department</span>
                                <span css={tw`text-neutral-300`}>{ticket.department}</span>
                            </div>
                            {ticket.serverName && (
                                <div>
                                    <span css={tw`block text-neutral-500 font-medium mb-1`}>Related Server</span>
                                    <span css={tw`text-cyan-400 cursor-pointer hover:underline`}>{ticket.serverName}</span>
                                </div>
                            )}
                            <div>
                                <span css={tw`block text-neutral-500 font-medium mb-1`}>Status</span>
                                <span css={[ticket.status === 'open' ? tw`text-yellow-400 font-medium` : tw`text-green-400 font-medium`]}>{ticket.status === 'open' ? 'Open' : 'Closed'}</span>
                            </div>
                            <div>
                                <span css={tw`block text-neutral-500 font-medium mb-1`}>Created At</span>
                                <span css={tw`text-neutral-300`}>{format(ticket.createdAt, 'MMM dd, yyyy - HH:mm')}</span>
                            </div>
                        </div>
                    </ContentBox>
                </div>
            </div>
        </PageContentBlock>
    );
};
