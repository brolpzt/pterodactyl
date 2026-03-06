import React, { useEffect, useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArrowLeft, faPaperclip } from '@fortawesome/free-solid-svg-icons';
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
import ContentBox from '@/components/elements/ContentBox';

export default () => {
    const history = useHistory();
    const { addFlash, clearFlashes } = useFlash();
    const { data: departments } = useTicketDepartments();
    const [servers, setServers] = useState<{ items: Server[] } | null>(null);

    const [subject, setSubject] = useState('');
    const [departmentId, setDepartmentId] = useState('');
    const [relatedServer, setRelatedServer] = useState('');
    const [message, setMessage] = useState('');
    const [files, setFiles] = useState<FileList | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        getServers({}).then((data) => setServers({ items: data.items }));
    }, []);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes('support');
        setIsSubmitting(true);

        createTicket(subject, parseInt(departmentId), message, relatedServer ? parseInt(relatedServer) : null, files)
            .then((ticket) => {
                history.push(`/account/support/${ticket.id}`);
            })
            .catch((error) => {
                setIsSubmitting(false);
                addFlash({ type: 'error', title: 'Error', message: error.response?.data?.errors[0]?.detail || 'An error occurred.', key: 'support' });
            });
    };

    return (
        <PageContentBlock title={'Create Ticket'} showFlashKey={'support'}>
            <div css={tw`flex items-center mb-6`}>
                <Link to={'/account/support'}>
                    <Button color={'grey'} isSecondary css={tw`mr-4`}>
                        <FontAwesomeIcon icon={faArrowLeft} />
                    </Button>
                </Link>
                <h1 css={tw`text-2xl`}>Create Ticket</h1>
            </div>

            <ContentBox title={'Ticket Details'} showFlashes={'support'}>
                <form onSubmit={handleSubmit}>
                    <div css={tw`mb-6`}>
                        <Label>Subject</Label>
                        <Input
                            value={subject}
                            onChange={(e) => setSubject(e.target.value)}
                            placeholder={'Subject'}
                            required
                        />
                    </div>
                    <div css={tw`flex flex-wrap mb-6`}>
                        <div css={tw`w-full md:flex-1 md:mr-4`}>
                            <Label>Department</Label>
                            {!departments ? (
                                <Spinner size={'small'} />
                            ) : (
                                <Select value={departmentId} onChange={(e) => setDepartmentId(e.target.value)} required>
                                    <option value="" disabled>Select Department</option>
                                    {departments.map((dept) => (
                                        <option key={dept.id} value={dept.id}>{dept.name}</option>
                                    ))}
                                </Select>
                            )}
                        </div>
                        <div css={tw`w-full md:flex-1 mt-6 md:mt-0`}>
                            <Label>Related Server</Label>
                            <Select value={relatedServer} onChange={(e) => setRelatedServer(e.target.value)}>
                                <option value="">None</option>
                                {servers?.items.map((server) => (
                                    <option key={server.id} value={server.internalId}>{server.name}</option>
                                ))}
                            </Select>
                        </div>
                    </div>
                    <div css={tw`mb-6`}>
                        <Label>Message</Label>
                        <textarea
                            css={tw`p-3 w-full border border-neutral-700 bg-neutral-900 rounded-md shadow-inner text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-shadow h-48 resize-y`}
                            placeholder={'Message...'}
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                            required
                        />
                    </div>
                    <div css={tw`mb-6`}>
                        <Label>Attachments</Label>
                        <div css={tw`flex items-center`}>
                            <Input
                                type={'file'}
                                onChange={(e) => setFiles(e.target.files)}
                                css={tw`flex-1`}
                                multiple
                            />
                            <FontAwesomeIcon icon={faPaperclip} css={tw`ml-3 text-neutral-500`} />
                        </div>
                        <p css={tw`text-xs text-neutral-500 mt-1`}>You can select multiple files.</p>
                    </div>
                    <div css={tw`flex justify-end`}>
                        <Button type={'submit'} disabled={isSubmitting}>
                            Create Ticket
                        </Button>
                    </div>
                </form>
            </ContentBox>
        </PageContentBlock>
    );
};
