import type { CustomerTicket, WidgetConfig } from './types';

export class ValidationError extends Error {
    constructor(public readonly errors: Record<string, string[]>) {
        super(Object.values(errors)[0]?.[0] ?? 'Dados inválidos.');
    }
}

/**
 * Cliente da API do widget. Sem cookies (credentials: 'omit'): o acesso ao
 * chamado é pelo token na URL, e a API responde com CORS para qualquer site.
 */
export class WidgetApi {
    constructor(private readonly config: WidgetConfig) {}

    open(data: { name: string; email: string; subject: string; body: string }): Promise<CustomerTicket> {
        return this.request('POST', '/tickets', data);
    }

    show(token: string): Promise<CustomerTicket> {
        return this.request('GET', `/tickets/${encodeURIComponent(token)}`);
    }

    reply(token: string, body: string): Promise<CustomerTicket> {
        return this.request('POST', `/tickets/${encodeURIComponent(token)}/messages`, { body });
    }

    private async request(method: string, path: string, body?: unknown): Promise<CustomerTicket> {
        const response = await fetch(this.config.apiUrl + path, {
            method,
            credentials: 'omit',
            headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
            body: body ? JSON.stringify(body) : undefined,
        });

        if (response.status === 422) throw new ValidationError((await response.json()).errors);
        if (response.status === 404) throw new Error('not_found');
        if (!response.ok) throw new Error('Não foi possível falar com o atendimento. Tente de novo em instantes.');

        return response.json();
    }
}
