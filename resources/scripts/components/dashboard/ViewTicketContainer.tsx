import React, { useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowLeft, faPaperPlane, faPaperclip, faDownload, faUser, faShieldAlt, faCalendarAlt, faClock, faLifeRing } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import ContentBox from '@/components/elements/ContentBox';
import { Link, useParams } from 'react-router-dom';
import { useTicket, replyTicket, updateTicketStatus } from '@/api/account/tickets';
import useFlash from '@/plugins/useFlash';
import Spinner from '@/components/elements/Spinner';
import { formatDistanceToNow, format } from 'date-fns';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import styled from 'styled-components';

const MessageCard = styled.div<{ $isStaff: boolean }>`
    ${tw`p-6 rounded-lg border shadow-lg relative mb-6 transition-all duration-200`};
    background-color: ${props => props.$isStaff ? '#262626' : '#262626'};
    border-color: ${props => props.$isStaff ? '#06b6d4' : '#404040'};
    
    &:before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 6px;
        border-radius: 8px 0 0 8px;
        background-color: ${props => props.$isStaff ? '#06b6d4' : '#525252'};
    }
`;

const Badge = styled.span<{ $variant?: string }>`
    ${tw`px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider`};
    background-color: ${props => props.$variant === 'cyan' ? '#06b6d4' : '#404040'};
    color: ${props => props.$variant === 'cyan' ? '#ecfeff' : '#d4d4d4'};
`;

const InfoCard = styled.div`
    ${tw`bg-neutral-800 border border-neutral-700 rounded-lg overflow-hidden shadow-xl`};
`;

const AttachmentItem = styled.a`
    ${tw`flex items-center bg-neutral-900 border border-neutral-700 p-2.5 rounded-lg text-xs text-neutral-400 transition-all no-underline shadow-sm`};
    
    &:hover {
        ${tw`text-cyan-400 border-cyan-500 bg-neutral-800`};
    }
    
    &:hover .dl-icon-box { background-color: rgba(6, 182, 212, 0.2); }
    &:hover .dl-icon { color: #22d3ee; }
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
                        <Button color={'grey'} isSecondary css={tw`mr-4 px-3 shadow-md`}>
                            <FontAwesomeIcon icon={faArrowLeft} />
                        </Button>
                    </Link>
                    <div>
                        <h1 css={tw`text-3xl font-black text-neutral-100 leading-none`}>{ticket.subject}</h1>
                        <div css={tw`flex items-center mt-2`}>
                            <Badge $variant={ticket.status === 'open' ? 'cyan' : undefined}>
                                {ticket.status.toUpperCase()}
                            </Badge>
                            <span css={tw`mx-2 text-neutral-600 font-bold`}>&bull;</span>
                            <span css={tw`text-[10px] text-neutral-400 uppercase tracking-widest font-bold`}>ID # {ticket.id}</span>
                        </div>
                    </div>
                </div>
                <Button
                    color={ticket.status === 'open' ? 'red' : 'green'}
                    isSecondary
                    onClick={toggleStatus}
                    css={tw`shadow-lg font-bold border-none transition-transform hover:scale-105 active:scale-95`}
                >
                    {ticket.status === 'open' ? 'Mark as Resolved' : 'Re-open Ticket'}
                </Button>
            </div>

            <div css={tw`flex flex-col-reverse md:flex-row gap-8 mb-10`}>
                <div css={tw`w-full md:w-2/3 flex flex-col`}>
                    <div css={tw`flex flex-col`}>
                        {ticket.messages?.map(msg => (
                            <MessageCard key={msg.id} $isStaff={msg.isStaff}>
                                <div css={tw`flex items-center justify-between mb-5`}>
                                    <div css={tw`flex items-center`}>
                                        <div css={[tw`h-10 w-10 rounded-lg flex items-center justify-center mr-4 text-xl shadow-inner border border-neutral-700`, msg.isStaff ? tw`bg-cyan-900 text-cyan-400` : tw`bg-neutral-900 text-neutral-400`]}>
                                            <FontAwesomeIcon icon={msg.isStaff ? faShieldAlt : faUser} />
                                        </div>
                                        <div>
                                            <h3 css={tw`text-base font-black text-neutral-50 leading-none mb-1.5`}>{msg.userName}</h3>
                                            <div css={tw`flex items-center gap-3`}>
                                                <Badge $variant={msg.isStaff ? 'cyan' : undefined}>{msg.isStaff ? 'Support' : 'Customer'}</Badge>
                                                <span css={tw`text-[10px] text-neutral-500 font-bold uppercase tracking-wider`}>
                                                    <FontAwesomeIcon icon={faClock} css={tw`mr-1.5 opacity-40`} />
                                                    {formatDistanceToNow(msg.createdAt, { addSuffix: true })}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div css={tw`text-[15px] text-neutral-300 leading-relaxed whitespace-pre-wrap pl-14 font-medium`}>{msg.message}</div>
                                {msg.attachments && msg.attachments.length > 0 && (
                                    <div css={tw`mt-8 pt-6 border-t border-neutral-700 ml-14`}>
                                        <p css={tw`text-[10px] font-black uppercase text-neutral-500 tracking-widest mb-4`}>Linked Files</p>
                                        <div css={tw`flex flex-wrap gap-3`}>
                                            {msg.attachments.map(att => (
                                                <AttachmentItem key={att.id} href={att.url} target={'_blank'} rel={'noreferrer'}>
                                                    <div className={'dl-icon-box'} css={tw`h-7 w-7 bg-neutral-800 rounded flex items-center justify-center mr-3 shadow-sm transition-colors`}>
                                                        <FontAwesomeIcon icon={faDownload} className={'dl-icon'} css={tw`text-xs text-neutral-600 transition-colors`} />
                                                    </div>
                                                    <div css={tw`flex flex-col`}>
                                                        <span css={tw`truncate max-w-[180px] font-bold`}>{att.filename}</span>
                                                        <span css={tw`text-[9px] opacity-40 uppercase font-black`}>{Math.round(att.size / 1024)} KB</span>
                                                    </div>
                                                </AttachmentItem>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </MessageCard>
                        ))}
                    </div>

                    {ticket.status === 'open' ? (
                        <div css={tw`mt-6`}>
                            <ContentBox showFlashes={'support'} css={tw`bg-neutral-800 shadow-2xl`}>
                                <div css={tw`flex items-center mb-6`}>
                                    <div css={tw`h-8 w-8 bg-cyan-700 text-cyan-100 rounded-md flex items-center justify-center mr-3 shadow-inner`}>
                                        <FontAwesomeIcon icon={faPaperPlane} css={tw`text-sm`} />
                                    </div>
                                    <h2 css={tw`text-neutral-50 text-xl font-black uppercase tracking-wider`}>Post a Reply</h2>
                                </div>
                                <form onSubmit={handleSubmitReply}>
                                    <textarea
                                        css={tw`p-5 w-full border-2 border-neutral-700 bg-neutral-900 rounded-xl shadow-inner text-base focus:outline-none focus:border-cyan-500 transition-all h-40 resize-y text-neutral-100 placeholder-neutral-600 font-medium`}
                                        placeholder={'Specify any error logs...'}
                                        value={reply}
                                        onChange={(e) => setReply(e.target.value)}
                                        required
                                    />
                                    <div css={tw`mt-6 p-5 bg-neutral-900 rounded-xl border-2 border-dashed border-neutral-700 transition-colors hover:border-cyan-500`}>
                                        <Label css={tw`mb-2 block text-[10px] font-bold text-neutral-500 uppercase tracking-widest`}>Attachments</Label>
                                        <div css={tw`flex items-center`}>
                                            <Input
                                                type={'file'}
                                                onChange={(e) => setFiles(e.target.files)}
                                                css={tw`flex-1 text-xs text-neutral-400 cursor-pointer`}
                                                multiple
                                            />
                                            <FontAwesomeIcon icon={faPaperclip} css={tw`ml-4 text-neutral-600`} />
                                        </div>
                                    </div>
                                    <div css={tw`flex justify-end mt-8`}>
                                        <Button type={'submit'} disabled={isSubmitting || !reply.trim()} css={tw`py-3 px-8 text-base shadow-xl border-none`}>
                                            <FontAwesomeIcon icon={faPaperPlane} css={tw`mr-3`} />
                                            Submit Reply
                                        </Button>
                                    </div>
                                </form>
                            </ContentBox>
                        </div>
                    ) : (
                        <div css={tw`mt-8 p-10 bg-neutral-800 border-2 border-dashed border-neutral-700 rounded-2xl flex flex-col items-center text-center shadow-lg`}>
                            <div css={tw`h-16 w-16 bg-neutral-700 rounded-full flex items-center justify-center mb-6`}>
                                <FontAwesomeIcon icon={faLifeRing} size={'2x'} opacity={0.3} />
                            </div>
                            <h3 css={tw`text-xl font-bold text-neutral-400 mb-2`}>Conversation Locked</h3>
                            <p css={tw`text-sm text-neutral-500 max-w-sm`}>This ticket has been resolved. If you need further assistance, please re-open the ticket.</p>
                        </div>
                    )}
                </div>

                <div css={tw`w-full md:w-1/3 flex flex-col gap-6`}>
                    <InfoCard>
                        <div css={tw`p-5 bg-neutral-900 border-b border-neutral-700`}><h2 css={tw`text-sm font-black text-neutral-100 uppercase tracking-widest`}>Management</h2></div>
                        <div css={tw`flex flex-col`}>
                            <div css={tw`flex items-center justify-between p-4 border-b border-neutral-700 hover:bg-neutral-700 transition-colors`}>
                                <span css={tw`text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>Status</span>
                                <span css={[tw`text-sm font-semibold`, { color: ticket.status === 'open' ? '#06b6d4' : '#10b981' }]}>{ticket.status.toUpperCase()}</span>
                            </div>
                            <div css={tw`flex items-center justify-between p-4 border-b border-neutral-700 hover:bg-neutral-700 transition-colors`}>
                                <span css={tw`text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>Department</span>
                                <span css={tw`text-sm font-semibold text-neutral-100`}>{ticket.department}</span>
                            </div>
                            {ticket.serverName && (
                                <div css={tw`flex items-center justify-between p-4 border-b border-neutral-700 hover:bg-neutral-700 transition-colors`}>
                                    <span css={tw`text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>Environment</span>
                                    <span css={tw`text-sm font-semibold text-neutral-100`}>{ticket.serverName}</span>
                                </div>
                            )}
                            <div css={tw`flex items-center justify-between p-4 border-b border-neutral-700 hover:bg-neutral-700 transition-colors`}>
                                <span css={tw`text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>Created</span>
                                <span css={tw`text-sm font-semibold text-neutral-100`}>{format(ticket.createdAt, 'MMM dd, yyyy')}</span>
                            </div>
                            <div css={tw`flex items-center justify-between p-4 hover:bg-neutral-700 transition-colors`}>
                                <span css={tw`text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>Activity</span>
                                <span css={tw`text-sm font-semibold text-neutral-100`}>{formatDistanceToNow(new Date(ticket.updatedAt), { addSuffix: true })}</span>
                            </div>
                        </div>
                    </InfoCard>
                    <div css={tw`p-6 bg-neutral-800 rounded-lg border border-neutral-700 relative overflow-hidden shadow-lg`}>
                        <h4 css={tw`text-cyan-400 text-[10px] font-black uppercase tracking-widest mb-4`}>Support Tip</h4>
                        <p css={tw`text-sm text-neutral-400 leading-relaxed font-medium`}>Attaching logs and specific error messages helps us resolve your issue <span css={tw`text-cyan-400 font-bold`}>faster</span>.</p>
                    </div>
                </div>
            </div>
        </PageContentBlock>
    );
};
