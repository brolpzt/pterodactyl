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
import { useTranslation } from 'react-i18next';

const AttachmentItem = styled.a`
    ${tw`flex items-center bg-neutral-900 border border-neutral-700 p-2.5 rounded-lg text-xs text-neutral-400 transition-all no-underline shadow-sm`};
    &:hover { ${tw`text-neutral-100 border-neutral-600 bg-neutral-800`}; }
    &:hover .dl-icon-box { background-color: rgba(255, 255, 255, 0.05); }
    &:hover .dl-icon { color: #d4d4d4; }
`;

const StatusLabel = styled.span<{ $isOpen: boolean }>`
    ${tw`px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider`};
    ${props => props.$isOpen ? tw`bg-green-600 text-green-50` : tw`bg-neutral-600 text-neutral-100`};
`;

export default () => {
    const { id } = useParams<{ id: string }>();
    const ticketId = parseInt(id);
    const { addFlash, clearFlashes } = useFlash();
    const { data: ticket, error, mutate } = useTicket(ticketId);
    const { t } = useTranslation('strings');

    const [reply, setReply] = useState('');
    const [files, setFiles] = useState<FileList | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const translateStatus = (status: string) => (status === 'open' ? t('support.status_open') : t('support.status_closed'));
    const formatTicketId = (value: number) => `#${String(value).padStart(4, '0')}`;

    const handleSubmitReply = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes('support');
        setIsSubmitting(true);

        replyTicket(ticketId, reply, files)
            .then(() => {
                setReply('');
                setFiles(null);
                mutate();
                addFlash({ type: 'success', title: t('support.success_title'), message: t('support.reply_success'), key: 'support' });
                setIsSubmitting(false);
            })
            .catch((error) => {
                setIsSubmitting(false);
                addFlash({
                    type: 'error',
                    title: t('support.error_title'),
                    message: error.response?.data?.errors[0]?.detail || t('support.generic_error'),
                    key: 'support',
                });
            });
    };

    const toggleStatus = () => {
        if (!ticket) return;
        clearFlashes('support');
        const newStatus = ticket.status === 'open' ? 'closed' : 'open';

        updateTicketStatus(ticketId, newStatus)
            .then(() => {
                mutate();
                addFlash({ type: 'success', title: t('support.success_title'), message: t('support.status_updated', { status: newStatus }), key: 'support' });
            })
            .catch((error) => {
                addFlash({
                    type: 'error',
                    title: t('support.error_title'),
                    message: error.response?.data?.errors[0]?.detail || t('support.generic_error'),
                    key: 'support',
                });
            });
    };

    if (error) return <PageContentBlock title={t('support.error_title')}><p css={tw`text-center text-red-500`}>{t('support.load_failed')}</p></PageContentBlock>;
    if (!ticket) return <PageContentBlock title={t('support.loading')}><Spinner size={'large'} centered /></PageContentBlock>;

    return (
        <PageContentBlock title={`${ticket.subject} (Ticket ${formatTicketId(ticket.id)})`} showFlashKey={'support'}>
            <div css={tw`flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4`}>
                <div css={tw`flex items-center`}>
                    <Link to={'/account/support'}>
                        <Button color={'grey'} isSecondary css={tw`mr-4 px-3`}>
                            <FontAwesomeIcon icon={faArrowLeft} />
                        </Button>
                    </Link>
                    <div>
                        <h1 css={tw`text-2xl font-black text-neutral-100 leading-none`}>{ticket.subject}</h1>
                        <div css={tw`flex items-center mt-2`}>
                            <span css={tw`text-[10px] text-neutral-400 uppercase tracking-widest font-bold`}>ID {formatTicketId(ticket.id)}</span>
                            <span css={tw`mx-2 text-neutral-600 font-bold`}>&bull;</span>
                            <span css={tw`text-[10px] text-neutral-500 uppercase tracking-widest font-bold`}>{translateStatus(ticket.status)}</span>
                        </div>
                    </div>
                </div>
                <div css={tw`flex gap-3`}>
                    <Button
                        color={'primary'}
                        onClick={toggleStatus}
                    >
                        {ticket.status === 'open' ? t('support.mark_resolved') : t('support.reopen_ticket')}
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
                                            <span css={tw`text-sm uppercase text-neutral-100`}>{msg.userName}</span>
                                            {msg.isStaff && (
                                                <span css={tw`ml-2 px-1.5 py-0.5 rounded bg-neutral-600 text-[10px] text-neutral-100 font-bold uppercase tracking-wider`}>
                                                    {t('support.staff')}
                                                </span>
                                            )}
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
                                        <p css={tw`text-[10px] font-black uppercase text-neutral-500 tracking-widest mb-3`}>{t('support.linked_files')}</p>
                                        <div css={tw`flex flex-wrap gap-2`}>
                                            {msg.attachments.map(att => (
                                                <AttachmentItem key={att.id} href={att.url} target={'_blank'} rel={'noreferrer'}>
                                                    <div className={'dl-icon-box'} css={tw`h-6 w-6 bg-neutral-800 rounded flex items-center justify-center mr-2 transition-colors`}>
                                                        <FontAwesomeIcon icon={faDownload} className={'dl-icon'} css={tw`text-[10px] text-neutral-600 transition-colors`} />
                                                    </div>
                                                    <div css={tw`flex flex-col`}>
                                                        <span css={tw`truncate max-w-[150px] font-bold`}>{att.filename}</span>
                                                        <span css={tw`text-[8px] opacity-40 uppercase font-black`}>{Math.round(att.size / 1024) * 1} KB</span>
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
                                        <span css={tw`text-sm uppercase`}>{t('support.post_reply')}</span>
                                    </div>
                                }
                            >
                                <form onSubmit={handleSubmitReply}>
                                    <textarea
                                        css={tw`p-5 w-full border-2 border-neutral-700 bg-neutral-900 rounded-xl text-sm focus:outline-none focus:border-neutral-600 transition-all h-40 resize-y text-neutral-100 placeholder-neutral-600 font-medium`}
                                        placeholder={t('support.message_placeholder')}
                                        value={reply}
                                        onChange={(e) => setReply(e.target.value)}
                                        required
                                    />
                                    <div css={tw`mt-4 p-4 bg-neutral-900 rounded-xl border-2 border-dashed border-neutral-700 transition-colors hover:border-neutral-600`}>
                                        <Label css={tw`mb-2 block text-[10px] font-bold text-neutral-500 uppercase tracking-widest`}>{t('support.attachments')}</Label>
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
                                            {t('support.submit_reply')}
                                        </Button>
                                    </div>
                                </form>
                            </TitledGreyBox>
                        </div>
                    ) : (
                        <div css={tw`mt-10 p-10 bg-neutral-800 border-2 border-dashed border-neutral-700 rounded-2xl flex flex-col items-center text-center text-neutral-500`}>
                            <FontAwesomeIcon icon={faLifeRing} size={'2x'} css={tw`mb-4 opacity-30`} />
                            <h3 css={tw`text-lg font-bold text-neutral-400 mb-2 uppercase tracking-wide`}>{t('support.ticket_resolved')}</h3>
                            <p css={tw`text-xs max-w-sm`}>{t('support.ticket_locked_description')}</p>
                        </div>
                    )}
                </div>

                <div css={tw`w-full md:w-1/3 flex flex-col gap-6`}>
                    <TitledGreyBox
                        title={
                            <div css={tw`flex items-center`}>
                                <span css={tw`text-sm uppercase`}>{t('support.ticket_details')}</span>
                            </div>
                        }
                    >
                        <table css={tw`w-full text-left`}>
                            <tbody>
                                <tr css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                    <td css={tw`px-3 py-3 text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>{t('support.status')}</td>
                                    <td css={tw`px-3 py-3 text-right text-sm font-bold text-neutral-100`}>
                                        <StatusLabel $isOpen={ticket.status === 'open'}>
                                            {translateStatus(ticket.status)}
                                        </StatusLabel>
                                    </td>
                                </tr>
                                <tr css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                    <td css={tw`px-3 py-3 text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>{t('support.department')}</td>
                                    <td css={tw`px-3 py-3 text-right text-sm font-semibold text-neutral-100`}>{ticket.department}</td>
                                </tr>
                                {ticket.serverName && (
                                    <tr css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                        <td css={tw`px-3 py-3 text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>{t('support.server')}</td>
                                        <td css={tw`px-3 py-3 text-right text-sm font-semibold text-neutral-100`}>{ticket.serverName}</td>
                                    </tr>
                                )}
                                <tr css={tw`border-b border-neutral-600 last:border-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                                    <td css={tw`px-3 py-3 text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>{t('support.created')}</td>
                                    <td css={tw`px-3 py-3 text-right text-sm font-semibold text-neutral-100`}>{format(ticket.createdAt, 'MMM dd, yyyy')}</td>
                                </tr>
                                <tr css={tw`hover:bg-neutral-600/20 transition-colors duration-100`}>
                                    <td css={tw`px-3 py-3 text-neutral-400 text-[11px] font-bold uppercase tracking-wider`}>{t('support.activity')}</td>
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
