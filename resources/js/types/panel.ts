export type TenantRole = 'owner' | 'admin' | 'agent';

export type TicketStatus = 'new' | 'open' | 'pending' | 'resolved' | 'closed';

export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';

export interface Panel {
    tenants: { id: number; name: string }[];
    current: { id: number; name: string; role: TenantRole };
}

/** Payload do evento "ticket.changed" (sem conteúdo: só o que mudou). */
export interface TicketChangedPayload {
    ticket_id: number;
    number: number;
    change: 'created' | 'message' | 'status' | 'assignment';
}

export interface TicketListItem {
    id: number;
    number: number;
    subject: string;
    customer: string;
    status: TicketStatus;
    status_label: string;
    priority: TicketPriority;
    priority_label: string;
    assignee: string | null;
    last_activity_at: string;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

export interface TicketDetail {
    id: number;
    number: number;
    subject: string;
    status: TicketStatus;
    status_label: string;
    priority: TicketPriority;
    priority_label: string;
    assignee_id: number | null;
    created_at: string;
    first_response_at: string | null;
    customer: { name: string; email: string };
    transitions: { value: TicketStatus; label: string }[];
    accepts_messages: boolean;
}

export interface TicketMessage {
    id: number;
    kind: 'reply' | 'note';
    body: string;
    author: string;
    author_type: 'agent' | 'customer' | 'system';
    created_at: string;
}

export interface TicketHistoryEntry {
    id: number;
    text: string;
    created_at: string;
}
