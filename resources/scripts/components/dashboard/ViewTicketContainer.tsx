import React, { useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowLeft, faPaperPlane, faPaperclip, faDownload } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import ContentBox from '@/components/elements/ContentBox';
import { Link, useParams } from 'react-router-dom';
import { useTicket, replyTicket, updateTicketStatus } from '@/api/account/tickets';
import useFlash from '@/plugins/useFlash';
import Spinner from '@/components/elements/Spinner';
import { formatDistanceToNow, format } from 'date-fns';
import Input from '@/components/elements/Input';

export default () => {
    const { id } = useParams<{ id: string }>();
    const ticketId = parseInt(id);
    const { addFlash, clearFlashes } = useFlash();
    const { data: ticket, error, mutate } = useTicket(ticketId);

    const [reply, setReply] = useState('');
    const [files, setFiles] = useState<FileList | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmitReply = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes('support');
        setIsSubmitting(true);

        replyTicket(ticketId, reply, files)
            .then(() => {
                setReply('');
                setFiles(null);
                mutate();
                addFlash({ type: 'success', title: 'Success', message: 'Reply added successfully.', key: 'support' });
                setIsSubmitting(false);
            })
            .catch((error) => {
                setIsSubmitting(false);
                addFlash({ type: 'error', title: 'Error', message: error.response?.data?.errors[0]?.detail || 'An error occurred.', key: 'support' });
            });
    };

    const toggleStatus = () => {
        if (!ticket) return;
        clearFlashes('support');
        const newStatus = ticket.status === 'open' ? 'closed' : 'open';

        updateTicketStatus(ticketId, newStatus)
            .then(() => {
                mutate();
                addFlash({ type: 'success', title: 'Success', message: `Ticket ${newStatus} successfully.`, key: 'support' });
            })
            .catch((error) => {
                addFlash({ type: 'error', title: 'Error', message: error.response?.data?.errors[0]?.detail || 'An error occurred.', key: 'support' });
            });
    };

    if (error) {
        return (
            <PageContentBlock title={'Error'}>
                <p css={tw`text-center text-red-500`}>Failed to load ticket.</p>
            </PageContentBlock>
        );
    }

    if (!ticket) {
        return (
            <PageContentBlock title={'Loading...'}>
                <Spinner size={'large'} centered />
            </PageContentBlock>
        );
    }

    return (
        <PageContentBlock title={`Ticket #${ticket.id}`} showFlashKey={'support'}>
            <div css={tw`flex items-center justify-between mb-6`}>
                <div css={tw`flex items-center`}>
                    <Link to={'/account/support'}>
                        <Button color={'grey'} isSecondary css={tw`mr-4`}>
                            <FontAwesomeIcon icon={faArrowLeft} />
                        </Button>
                    </Link>
                    <h1 css={tw`text-2xl`}>{ticket.subject}</h1>
                </div>
                <Button color={ticket.status === 'open' ? 'red' : 'green'} isSecondary onClick={toggleStatus}>
                    {ticket.status === 'open' ? 'Close Ticket' : 'Re-open Ticket'}
                </Button>
            </div>

            <div css={tw`grid grid-cols-1 md:grid-cols-3 gap-6 mb-10`}>
                <div css={tw`md:col-span-2 space-y-4`}>
                    {ticket.messages?.map(msg => (
                        <ContentBox key={msg.id} title={msg.userName} css={msg.isStaff ? tw`bg-neutral-700/50` : undefined}>
                            <div css={tw`flex justify-between items-start mb-4 border-b border-neutral-700 pb-2`}>
                                <span css={tw`text-xs uppercase font-bold text-neutral-400`}>
                                    {msg.isStaff ? 'Staff' : 'User'} &bull; {formatDistanceToNow(msg.createdAt, { addSuffix: true })}
                                </span>
                            </div>
                            <p css={tw`text-sm whitespace-pre-wrap`}>{msg.message}</p>

                            {msg.attachments && msg.attachments.length > 0 && (
                                <div css={tw`mt-4 pt-4 border-t border-neutral-700`}>
                                    <p css={tw`text-xs font-bold uppercase text-neutral-400 mb-2`}>Attachments</p>
                                    <div css={tw`flex flex-wrap gap-2`}>
                                        {msg.attachments.map(att => (
                                            <a
                                                key={att.id}
                                                href={att.url}
                                                target={'_blank'}
                                                rel={'noreferrer'}
                                                css={tw`flex items-center bg-neutral-900 border border-neutral-700 px-3 py-1.5 rounded text-xs text-neutral-300 hover:text-white hover:border-neutral-500 transition-colors no-underline`}
                                            >
                                                <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                                                {att.filename} ({Math.round(att.size / 1024)} KB)
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </ContentBox>
                    ))}

                    {ticket.status === 'open' && (
                        <ContentBox title={'Reply'} showFlashes={'support'}>
                            <form onSubmit={handleSubmitReply}>
                                <textarea
                                    css={tw`p-3 w-full border border-neutral-700 bg-neutral-900 rounded-md shadow-inner text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-shadow h-32 resize-y`}
                                    placeholder={'Reply...'}
                                    value={reply}
                                    onChange={(e) => setReply(e.target.value)}
                                    required
                                />
                                <div css={tw`mt-4 mb-4`}>
                                    <div css={tw`flex items-center`}>
                                        <Input
                                            type={'file'}
                                            onChange={(e) => setFiles(e.target.files)}
                                            css={tw`flex-1`}
                                            multiple
                                        />
                                        <FontAwesomeIcon icon={faPaperclip} css={tw`ml-3 text-neutral-500`} />
                                    </div>
                                </div>
                                <div css={tw`flex justify-end mt-4`}>
                                    <Button type={'submit'} disabled={isSubmitting || !reply.trim()}>
                                        <FontAwesomeIcon icon={faPaperPlane} css={tw`mr-2`} />
                                        Send Reply
                                    </Button>
                                </div>
                            </form>
                        </ContentBox>
                    )}
                </div>

                <div css={tw`space-y-6`}>
                    <ContentBox title={'Information'}>
                        <div css={tw`space-y-4 text-sm`}>
                            <div>
                                <p css={tw`text-neutral-500 mb-0 font-bold uppercase text-[10px]`}>Status</p>
                                <p css={ticket.status === 'open' ? tw`text-yellow-500` : tw`text-green-500`}>{ticket.status.toUpperCase()}</p>
                            </div>
                            <div>
                                <p css={tw`text-neutral-500 mb-0 font-bold uppercase text-[10px]`}>Department</p>
                                <p>{ticket.department}</p>
                            </div>
                            {ticket.serverName && (
                                <div>
                                    <p css={tw`text-neutral-500 mb-0 font-bold uppercase text-[10px]`}>Server</p>
                                    <p>{ticket.serverName}</p>
                                </div>
                            )}
                            <div>
                                <p css={tw`text-neutral-500 mb-0 font-bold uppercase text-[10px]`}>Created At</p>
                                <p>{format(ticket.createdAt, 'MMM dd, yyyy HH:mm')}</p>
                            </div>
                        </div>
                    </ContentBox>
                </div>
            </div>
        </PageContentBlock>
    );
};
