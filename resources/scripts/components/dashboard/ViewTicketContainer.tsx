import React, { useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowLeft, faPaperPlane, faPaperclip, faDownload, faClock, faLifeRing } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import { Link, useParams } from 'react-router-dom';
import { useTicket, replyTicket, updateTicketStatus } from '@/api/account/tickets';
import useFlash from '@/plugins/useFlash';
import Spinner from '@/components/elements/Spinner';
import { formatDistanceToNow, format } from 'date-fns';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import styled from 'styled-components';

const AttachmentItem = styled.a`
    ${tw`flex items-center bg-neutral-900 border border-neutral-700 p-2.5 rounded-lg text-xs text-neutral-400 transition-all no-underline shadow-sm`};
    &:hover { ${tw`text-neutral-100 border-neutral-600 bg-neutral-800`}; }
    &:hover .dl-icon-box { background-color: rgba(255, 255, 255, 0.05); }
    &:hover .dl-icon { color: #d4d4d4; }
`;

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
                addFlash({ type: 'success', title: 'Success', message: `Ticket status updated to ${newStatus}.`, key: 'support' });
            })
            .catch((error) => {
                addFlash({ type: 'error', title: 'Error', message: error.response?.data?.errors[0]?.detail || 'An error occurred.', key: 'support' });
            });
    };

    if (error) return <PageContentBlock title={'Error'}><p css={tw`text-center text-red-500`}>Failed to load ticket.</p></PageContentBlock>;
    if (!ticket) return <PageContentBlock title={'Loading...'}><Spinner size={'large'} centered /></PageContentBlock>;

    return (
        <PageContentBlock title={`${ticket.subject} (Ticket #${ticket.id})`} showFlashKey={'support'}>
            <div css={tw`flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4`}>
                <div css={tw`flex items-center`}>
                    <Link to={'/account/support'}>
                        <Button color={'grey'} isSecondary css={tw`mr-4 px-3`}>
                            <FontAwesomeIcon icon={faArrowLeft} />
                        </Button>
                    </Link>
                    <div>
                        <h1 css={tw`text-3xl font-black text-neutral-100 leading-none`}>{ticket.subject}</h1>
                        <div css={tw`flex items-center mt-2`}>
                            <span css={tw`text-[10px] text-neutral-400 uppercase tracking-widest font-bold`}>ID # {ticket.id}</span>
                            <span css={tw`mx-2 text-neutral-600 font-bold`}>&bull;</span>
                            <span css={tw`text-[10px] text-neutral-500 uppercase tracking-widest font-bold`}>{ticket.status.toUpperCase()}</span>
                        </div>
                    </div>
                </div>
                <div css={tw`flex gap-3`}>
                    <Button
                        color={ticket.status === 'open' ? 'red' : 'green'}
                        isSecondary
                        onClick={toggleStatus}
                    >
                        {ticket.status === 'open' ? 'Mark as Resolved' : 'Re-open Ticket'}
                    </Button>
                </div>
            </div>

            <div css={tw`flex flex-col-reverse md:flex-row gap-8 mb-10`}>
                <div css={tw`w-full md:w-2/3 flex flex-col gap-6`}>
                    <div css={tw`flex flex-col gap-6`}>
                        {ticket.messages?.map(msg => (
                            <TitledGreyBox
                                key={msg.id}
                                title={
                                    <div css={tw`flex items-center justify-between w-full`}>
                                        <div css={tw`flex items-center`}>
                                            <span css={tw`text-xs uppercase font-bold text-neutral-100`}>{msg.userName}</span>
                                            {msg.isStaff && <span css={tw`ml-2 px-1.5 py-0.5 rounded bg-neutral-600 text-[10px] text-neutral-100 font-bold uppercase tracking-wider`}>Staff</span>}
                                        </div>
                                        <div css={tw`flex items-center text-[10px] text-neutral-500 font-bold uppercase`}>
                                            <FontAwesomeIcon icon={faClock} css={tw`mr-1.5 opacity-40`} />
                                            {formatDistanceToNow(msg.createdAt, { addSuffix: true })}
                                        </div>
                                    </div>
                                }
                            >
                                <div css={tw`text-sm text-neutral-200 leading-relaxed whitespace-pre-wrap font-normal`}>
                                    {msg.message}
                                </div>

                                {msg.attachments && msg.attachments.length > 0 && (
                                    <div css={tw`mt-4 pt-4 border-t border-neutral-600`}>
                                        <p css={tw`text-[10px] font-black uppercase text-neutral-500 tracking-widest mb-3`}>Linked Files</p>
                                        <div css={tw`flex flex-wrap gap-2`}>
                                            {msg.attachments.map(att => (
                                                <AttachmentItem key={att.id} href={att.url} target={'_blank'} rel={'noreferrer'}>
                                                    <div className={'dl-icon-box'} css={tw`h-6 w-6 bg-neutral-800 rounded flex items-center justify-center mr-2 transition-colors`}>
                                                        <FontAwesomeIcon icon={faDownload} className={'dl-icon'} css={tw`text-[10px] text-neutral-600 transition-colors`} />
                                                    </div>
                                                    <div css={tw`flex flex-col`}>
                                                        <span css={tw`truncate max-w-[150px] font-bold`}>{att.filename}</span>
                                                        <span css={tw`text-[8px] opacity-40 uppercase font-black`}>{Math.round(att.size / 1024)} KB</span>
                                                    </div>
                                                </AttachmentItem>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </TitledGreyBox>
                        ))}
                    </div>

                    {ticket.status === 'open' ? (
                        <div css={tw`mt-10`}>
                            <TitledGreyBox
                                title={
                                    <div css={tw`flex items-center`}>
                                        <span css={tw`text-xs uppercase font-bold text-neutral-100`}>Post a Reply</span>
                                    </div>
                                }
                            >
                                <form onSubmit={handleSubmitReply}>
                                    <textarea
                                        css={tw`p-5 w-full border-2 border-neutral-700 bg-neutral-900 rounded-xl text-sm focus:outline-none focus:border-neutral-600 transition-all h-40 resize-y text-neutral-100 placeholder-neutral-600 font-medium`}
                                        placeholder={'Specify any details...'}
                                        value={reply}
                                        onChange={(e) => setReply(e.target.value)}
                                        required
                                    />
                                    <div css={tw`mt-4 p-4 bg-neutral-900 rounded-xl border-2 border-dashed border-neutral-700 transition-colors hover:border-neutral-600`}>
                                        <Label css={tw`mb-2 block text-[10px] font-bold text-neutral-500 uppercase tracking-widest`}>Attachments</Label>
                                        <div css={tw`flex items-center`}>
                                            <input
                                                type={'file'}
                                                onChange={(e) => setFiles(e.target.files)}
                                                css={tw`flex-1 text-xs text-neutral-400 cursor-pointer`}
                                                multiple
                                            />
                                            <FontAwesomeIcon icon={faPaperclip} css={tw`ml-4 text-neutral-600`} />
                                        </div>
                                    </div>
                                    <div css={tw`flex justify-end mt-6`}>
                                        <Button type={'submit'} disabled={isSubmitting || !reply.trim()} css={tw`py-2.5 px-6`}>
                                            <FontAwesomeIcon icon={faPaperPlane} css={tw`mr-2`} />
                                            Submit Reply
                                        </Button>
                                    </div>
                                </form>
                            </TitledGreyBox>
                        </div>
                    ) : (
                        <div css={tw`mt-10 p-10 bg-neutral-800 border-2 border-dashed border-neutral-700 rounded-2xl flex flex-col items-center text-center text-neutral-500`}>
                            <FontAwesomeIcon icon={faLifeRing} size={'2x'} css={tw`mb-4 opacity-30`} />
                            <h3 css={tw`text-lg font-bold text-neutral-400 mb-2 uppercase tracking-wide`}>Ticket Resolved</h3>
                            <p css={tw`text-xs max-w-sm`}>This ticket has been marked as resolved and is now locked for further conversation.</p>
                        </div>
                    )}
                </div>

                <div css={tw`w-full md:w-1/3 flex flex-col gap-6`}>
                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center`}>
                                <span css={tw`text-xs uppercase font-bold text-neutral-100`}>Management</span>
                            </div>
                        }
                    >
                        <table css={tw`w-full text-left`}>
                            <tbody>
                                <tr css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                    <td css={tw`px-3 py-3 text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>Status</td>
                                    <td css={tw`px-3 py-3 text-right text-sm font-bold text-neutral-100`}>
                                        {ticket.status.toUpperCase()}
                                    </td>
                                </tr>
                                <tr css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                    <td css={tw`px-3 py-3 text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>Department</td>
                                    <td css={tw`px-3 py-3 text-right text-sm font-semibold text-neutral-100`}>{ticket.department}</td>
                                </tr>
                                {ticket.serverName && (
                                    <tr css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                        <td css={tw`px-3 py-3 text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>Instance</td>
                                        <td css={tw`px-3 py-3 text-right text-sm font-semibold text-neutral-100`}>{ticket.serverName}</td>
                                    </tr>
                                )}
                                <tr css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                    <td css={tw`px-3 py-3 text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>Created</td>
                                    <td css={tw`px-3 py-3 text-right text-sm font-semibold text-neutral-100`}>{format(ticket.createdAt, 'MMM dd, yyyy')}</td>
                                </tr>
                                <tr css={tw`hover:bg-neutral-600/20 transition-colors duration-100`}>
                                    <td css={tw`px-3 py-3 text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>Activity</td>
                                    <td css={tw`px-3 py-3 text-right text-sm font-semibold text-neutral-100`}>{formatDistanceToNow(new Date(ticket.updatedAt), { addSuffix: true })}</td>
                                </tr>
                            </tbody>
                        </table>
                    </TitledGreyBox>
                </div>
            </div>
        </PageContentBlock>
    );
};
