import React, { useEffect, useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPaperPlane, faArrowLeft, faTags, faServer, faFileAlt } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import { Link, useHistory } from 'react-router-dom';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { createTicket, useTicketDepartments } from '@/api/account/tickets';
import useFlash from '@/plugins/useFlash';
import getServers from '@/api/getServers';
import { Server } from '@/api/server/getServer';
import Spinner from '@/components/elements/Spinner';

export default () => {
    const history = useHistory();
    const { addFlash, clearFlashes } = useFlash();
    const { data: departments } = useTicketDepartments();
    const [servers, setServers] = useState<{ items: Server[] } | null>(null);

    const [subject, setSubject] = useState('');
    const [departmentId, setDepartmentId] = useState('');
    const [relatedServer, setRelatedServer] = useState('');
    const [message, setMessage] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        getServers({}).then((data) => setServers({ items: data.items }));
    }, []);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes('support');
        setIsSubmitting(true);

        createTicket(subject, parseInt(departmentId), message, relatedServer ? parseInt(relatedServer) : null)
            .then((ticket) => {
                addFlash({ type: 'success', title: 'Success', message: 'Your support ticket has been created.', key: 'support' });
                history.push(`/account/support/${ticket.id}`);
            })
            .catch((error) => {
                setIsSubmitting(false);
                addFlash({ type: 'error', title: 'Error', message: error.response?.data?.errors[0]?.detail || 'An error occurred while creating the ticket.', key: 'support' });
            });
    };

    return (
        <PageContentBlock title={'Create New Ticket'}>
            <div css={tw`flex items-center mb-10`}>
                <Link to={'/account/support'}>
                    <Button color={'grey'} isSecondary css={tw`mr-6 px-4 py-3 bg-neutral-800 shadow-md`}>
                        <FontAwesomeIcon icon={faArrowLeft} />
                    </Button>
                </Link>
                <div>
                    <h1 css={tw`text-3xl font-black text-neutral-100 tracking-tight`}>Open Support Ticket</h1>
                    <p css={tw`text-neutral-400 mt-1`}>Fill out the form below to contact our support team.</p>
                </div>
            </div>

            <div css={tw`grid grid-cols-1 lg:grid-cols-3 gap-10`}>
                <div css={tw`lg:col-span-2`}>
                    <div css={tw`bg-neutral-800/50 border border-neutral-700/50 rounded-2xl shadow-xl overflow-hidden`}>
                        <form onSubmit={handleSubmit} css={tw`p-8`}>
                            <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-6 mb-8`}>
                                <div css={tw`md:col-span-2`}>
                                    <Label css={tw`text-neutral-200 mb-2 block font-bold text-sm tracking-wide`}><FontAwesomeIcon icon={faFileAlt} css={tw`mr-2 text-neutral-500`} /> Ticket Subject</Label>
                                    <Input
                                        value={subject}
                                        onChange={(e) => setSubject(e.target.value)}
                                        placeholder={'Briefly describe your issue...'}
                                        required
                                        css={tw`bg-neutral-900 border-neutral-700 focus:ring-2 focus:ring-cyan-500/50 transition-all py-3`}
                                    />
                                </div>
                                <div>
                                    <Label css={tw`text-neutral-200 mb-2 block font-bold text-sm tracking-wide`}><FontAwesomeIcon icon={faTags} css={tw`mr-2 text-neutral-500`} /> Department</Label>
                                    {!departments ? (
                                        <div css={tw`h-10 w-full bg-neutral-900 rounded border border-neutral-700 flex items-center px-4`}>
                                            <Spinner size={'small'} />
                                        </div>
                                    ) : (
                                        <Select value={departmentId} onChange={(e) => setDepartmentId(e.target.value)} required css={tw`bg-neutral-900 border-neutral-700`}>
                                            <option value="" disabled>-- Select Category --</option>
                                            {departments.map((dept) => (
                                                <option key={dept.id} value={dept.id}>{dept.name}</option>
                                            ))}
                                        </Select>
                                    )}
                                </div>
                                <div>
                                    <Label css={tw`text-neutral-200 mb-2 block font-bold text-sm tracking-wide`}><FontAwesomeIcon icon={faServer} css={tw`mr-2 text-neutral-500`} /> Related Server</Label>
                                    <Select value={relatedServer} onChange={(e) => setRelatedServer(e.target.value)} css={tw`bg-neutral-900 border-neutral-700`}>
                                        <option value="">-- No Related Server --</option>
                                        {servers?.items.map((server) => (
                                            <option key={server.id} value={server.internalId}>{server.name}</option>
                                        ))}
                                    </Select>
                                </div>
                            </div>

                            <div css={tw`mb-8`}>
                                <Label css={tw`text-neutral-200 mb-2 block font-bold text-sm tracking-wide`}>Detailed Message</Label>
                                <textarea
                                    css={tw`p-4 w-full border border-neutral-700 bg-neutral-900 rounded-xl shadow-inner text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all h-60 resize-y text-neutral-200 font-sans`}
                                    placeholder={'Describe your problem or request in detail. Including error messages or steps to reproduce the issue will help us assist you faster.'}
                                    value={message}
                                    onChange={(e) => setMessage(e.target.value)}
                                    required
                                />
                            </div>

                            <div css={tw`flex justify-end pt-4 border-t border-neutral-700/50`}>
                                <Button type={'submit'} color={'primary'} disabled={isSubmitting || !subject || !departmentId || !message} css={tw`px-8 py-3 shadow-lg`}>
                                    <FontAwesomeIcon icon={faPaperPlane} css={tw`mr-2`} />
                                    {isSubmitting ? 'Creating Ticket...' : 'Open Support Ticket'}
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                <div css={tw`lg:col-span-1 space-y-6`}>
                    <div css={tw`bg-cyan-900/10 border border-cyan-800/20 rounded-2xl p-8 shadow-sm`}>
                        <h4 css={tw`text-cyan-400 font-black text-lg mb-4 tracking-tight flex items-center`}>
                            <FontAwesomeIcon icon={faFileAlt} css={tw`mr-3 opacity-50`} />
                            Before You Submit
                        </h4>
                        <ul css={tw`space-y-4 text-sm text-neutral-400`}>
                            <li css={tw`flex items-start`}>
                                <div css={tw`w-1.5 h-1.5 bg-cyan-500 rounded-full mt-1.5 mr-3 flex-shrink-0`} />
                                Check our documentation/wiki for common issues.
                            </li>
                            <li css={tw`flex items-start`}>
                                <div css={tw`w-1.5 h-1.5 bg-cyan-500 rounded-full mt-1.5 mr-3 flex-shrink-0`} />
                                Be specific and include logs if possible.
                            </li>
                            <li css={tw`flex items-start`}>
                                <div css={tw`w-1.5 h-1.5 bg-cyan-500 rounded-full mt-1.5 mr-3 flex-shrink-0`} />
                                Select the correct department for faster routing.
                            </li>
                        </ul>
                    </div>

                    <div css={tw`bg-neutral-800/40 border border-neutral-700/50 rounded-2xl p-8`}>
                        <h4 css={tw`text-neutral-200 font-bold mb-3`}>Operating Hours</h4>
                        <p css={tw`text-xs text-neutral-400 leading-relaxed`}>
                            Our support team is available Mon-Fri, 9am - 6pm (UTC-3). Tickets opened outside these hours may experience longer response times.
                        </p>
                    </div>
                </div>
            </div>
        </PageContentBlock>
    );
};
