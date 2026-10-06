/** Configuração injetada pelo servidor antes do bundle (WidgetScriptController). */
export interface WidgetConfig {
    tenant: { slug: string; name: string };
    color: string;
    textColor: string;
    apiUrl: string;
    reverb: { key: string; host: string; port: number; scheme: string };
}

export interface CustomerTicket {
    ticket: {
        id: number;
        number: number;
        subject: string;
        status: string;
        status_label: string;
        accepts_messages: boolean;
        token: string;
    };
    messages: { id: number; body: string; author: string; from_customer: boolean; created_at: string }[];
}

declare global {
    interface Window {
        __HELPDESK_WIDGET__?: WidgetConfig;
        __HELPDESK_WIDGET_LOADED__?: boolean;
    }
}
