import React, { useState } from 'react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPaperPlane, faPaperclip } from '@fortawesome/free-solid-svg-icons';
import Button from '@/components/elements/Button';
import ContentBox from '@/components/elements/ContentBox';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { useHistory } from 'react-router-dom';
import useSWR from 'swr';
import getServers from '@/api/getServers';
import { createTicket, useTicketDepartments } from '@/api/account/tickets';
import useFlash from '@/plugins/useFlash';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';

export default () => {
    const history = useHistory();
    const { addFlash, clearFlashes } = useFlash();

    const { data: departments } = useTicketDepartments();
    const { data: servers } = useSWR(['/api/client'], () => getServers({}));

    const [departmentId, setDepartmentId] = useState('');
    const [subject, setSubject] = useState('');
    const [message, setMessage] = useState('');
    const [relatedServer, setRelatedServer] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes('support');

        if (!departmentId) {
            addFlash({ type: 'error', title: 'Error', message: 'You must select a department.', key: 'support' });
            return;
        }

        setIsSubmitting(true);
        createTicket(subject, parseInt(departmentId), message, relatedServer ? parseInt(relatedServer) : undefined)
            .then(() => {
                addFlash({ type: 'success', title: 'Success', message: 'Your ticket has been created successfully.', key: 'support' });
                history.push('/account/support');
            })
            .catch((error) => {
                setIsSubmitting(false);
                addFlash({ type: 'error', title: 'Error', message: error.response?.data?.errors[0]?.detail || 'An error occurred while creating your ticket.', key: 'support' });
            });
    };

    return (
        <PageContentBlock title={'Open New Ticket'}>
            <div css={tw`flex justify-between items-center mb-6`}>
                <h1 css={tw`text-2xl font-bold flex items-center`}>
                    Open New Ticket
                </h1>
                <Button color={'grey'} onClick={() => history.push('/account/support')}>
                    Cancel
                </Button>
            </div>

            <div css={tw`max-w-3xl mx-auto relative`}>
                <SpinnerOverlay visible={isSubmitting} />
                <ContentBox>
                    <form onSubmit={handleSubmit} css={tw`space-y-6`}>
                        <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                            <div>
                                <label css={tw`block text-sm font-medium text-neutral-300 mb-2`}>Department</label>
                                <Select value={departmentId} onChange={(e) => setDepartmentId(e.target.value)}>
                                    <option value="" disabled>-- Select a department --</option>
                                    {departments?.map((dept) => (
                                        <option key={dept.id} value={dept.id}>{dept.name}</option>
                                    ))}
                                </Select>
                            </div>

                            <div>
                                <label css={tw`block text-sm font-medium text-neutral-300 mb-2`}>Related Server</label>
                                <Select value={relatedServer} onChange={(e) => setRelatedServer(e.target.value)}>
                                    <option value="">-- None --</option>
                                    {servers?.items.map((server) => (
                                        <option key={server.id} value={server.id}>{server.name}</option>
                                    ))}
                                </Select>
                                <p css={tw`text-xs text-neutral-400 mt-1`}>Optional: Select the server you need help with.</p>
                            </div>
                        </div>

                        <div>
                            <label css={tw`block text-sm font-medium text-neutral-300 mb-2`}>Subject</label>
                            <Input
                                placeholder={'Brief summary of your issue'}
                                value={subject}
                                onChange={(e) => setSubject(e.target.value)}
                                required
                            />
                        </div>

                        <div>
                            <label css={tw`block text-sm font-medium text-neutral-300 mb-2`}>Description</label>
                            <textarea
                                css={tw`p-3 w-full border border-neutral-700 bg-neutral-900 rounded-md shadow-inner text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-shadow h-48 resize-y`}
                                placeholder={'Please descibe your issue in detail. If related to a crash, include error logs.'}
                                value={message}
                                onChange={(e) => setMessage(e.target.value)}
                                required
                            />
                        </div>

                        <div>
                            <label css={tw`block text-sm font-medium text-neutral-300 mb-2`}>Attachments (Optional)</label>
                            <label css={tw`flex items-center w-full px-4 py-3 bg-neutral-800 border-2 border-dashed border-neutral-600 rounded-lg cursor-pointer hover:border-cyan-500 hover:bg-neutral-800/80 transition-all`}>
                                <FontAwesomeIcon icon={faPaperclip} css={tw`text-cyan-400 mr-3`} />
                                <span css={tw`text-sm text-neutral-400`}>Click to browse or drag and drop files here to attach</span>
                                <input type='file' multiple css={tw`hidden`} />
                            </label>
                            <p css={tw`text-xs text-neutral-500 mt-2`}>Supported files: .jpg, .png, .pdf, .txt, .log (Max 5MB)</p>
                        </div>

                        <div css={tw`flex justify-end pt-4 border-t border-neutral-700`}>
                            <Button type="submit" color="primary" size="large" disabled={isSubmitting || !departmentId || !subject || !message}>
                                <FontAwesomeIcon icon={faPaperPlane} css={tw`mr-2`} />
                                Submit Ticket
                            </Button>
                        </div>
                    </form>
                </ContentBox>
            </div>
        </PageContentBlock>
    );
};
