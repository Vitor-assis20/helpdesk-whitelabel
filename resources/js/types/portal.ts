export interface Tenant {
    name: string;
    slug: string;
    branding: { primary_color?: string | null; logo_url?: string | null };
}

export interface PortalTicket {
    id: number;
    number: number;
    subject: string;
    status: 'new' | 'open' | 'pending' | 'resolved' | 'closed';
    status_label: string;
    accepts_messages: boolean;
    /** Token do link do cliente: autoriza o canal em tempo real do portal. */
    token: string;
}

export interface PortalMessage {
    id: number;
    body: string;
    author: string;
    from_customer: boolean;
    created_at: string;
}
