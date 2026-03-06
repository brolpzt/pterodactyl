import useSWR, { ConfigInterface } from 'swr';
import http, { FractalResponseData, FractalResponseList } from '@/api/http';
import { AxiosError } from 'axios';

export interface TicketDepartment {
    id: number;
    name: string;
    description: string | null;
}

export interface TicketAttachment {
    id: number;
    filename: string;
    size: number;
    url: string;
}

export interface TicketMessage {
    id: number;
    ticketId: number;
    userId: number;
    userName: string;
    userEmail: string;
    isStaff: boolean;
    message: string;
    createdAt: Date;
    updatedAt: Date;
    attachments?: TicketAttachment[];
}

export interface Ticket {
    id: number;
    subject: string;
    departmentId: number | null;
    department: string;
    status: string;
    serverId: number | null;
    serverName: string | null;
    createdAt: Date;
    updatedAt: Date;
    messages?: TicketMessage[];
}

const transformTicketDepartment = (data: any): TicketDepartment => ({
    id: data.id,
    name: data.name,
    description: data.description,
});

const transformTicketMessage = (data: any): TicketMessage => ({
    id: data.id,
    ticketId: data.ticket_id,
    userId: data.user_id,
    userName: data.user_name,
    userEmail: data.user_email,
    isStaff: data.is_staff,
    message: data.message,
    createdAt: new Date(data.created_at),
    updatedAt: new Date(data.updated_at),
    attachments: data.relationships?.attachments?.data ? data.relationships.attachments.data.map((att: any) => ({
        id: att.attributes.id,
        filename: att.attributes.filename,
        size: att.attributes.size,
        url: att.attributes.url,
    })) : undefined,
});

const transformTicket = (data: any): Ticket => ({
    id: data.id,
    subject: data.subject,
    departmentId: data.department_id,
    department: data.department,
    status: data.status,
    serverId: data.server_id,
    serverName: data.server_name,
    createdAt: new Date(data.created_at),
    updatedAt: new Date(data.updated_at),
    messages: data.relationships?.messages?.data ? data.relationships.messages.data.map((msg: any) => transformTicketMessage(msg.attributes)) : undefined,
});

export const useTickets = (config?: ConfigInterface<Ticket[], AxiosError>) => {
    return useSWR(
        ['account', 'tickets'],
        async () => {
            const { data } = await http.get('/api/client/account/tickets');
            return (data as FractalResponseList).data.map((datum: any) => transformTicket(datum.attributes));
        },
        { revalidateOnMount: true, ...(config || {}) }
    );
};

export const useTicket = (id: number, config?: ConfigInterface<Ticket, AxiosError>) => {
    return useSWR(
        ['account', 'tickets', id],
        async () => {
            const { data } = await http.get(`/api/client/account/tickets/${id}`);
            return transformTicket(data.attributes);
        },
        { revalidateOnMount: true, ...(config || {}) }
    );
};

export const useTicketDepartments = (config?: ConfigInterface<TicketDepartment[], AxiosError>) => {
    return useSWR(
        ['account', 'ticket-departments'],
        async () => {
            const { data } = await http.get('/api/client/account/tickets/departments');
            return (data as FractalResponseList).data.map((datum: any) => transformTicketDepartment(datum.attributes));
        },
        { revalidateOnMount: true, ...(config || {}) }
    );
};

export const createTicket = async (subject: string, departmentId: number, message: string, serverId?: number | null, files?: FileList | null): Promise<Ticket> => {
    const data = new FormData();
    data.append('subject', subject);
    data.append('department_id', departmentId.toString());
    data.append('message', message);
    if (serverId) data.append('server_id', serverId.toString());

    if (files) {
        for (let i = 0; i < files.length; i++) {
            data.append('attachments[]', files[i]);
        }
    }

    const { data: res } = await http.post('/api/client/account/tickets', data);

    return transformTicket(res.attributes);
};

export const replyTicket = async (id: number, message: string, files?: FileList | null): Promise<TicketMessage> => {
    const data = new FormData();
    data.append('message', message);

    if (files) {
        for (let i = 0; i < files.length; i++) {
            data.append('attachments[]', files[i]);
        }
    }

    const { data: res } = await http.post(`/api/client/account/tickets/${id}`, data);

    return transformTicketMessage(res.attributes);
};

export const updateTicketStatus = async (id: number, status: 'open' | 'closed'): Promise<Ticket> => {
    const { data } = await http.post(`/api/client/account/tickets/${id}/status`, { status });

    return transformTicket(data.attributes);
};
