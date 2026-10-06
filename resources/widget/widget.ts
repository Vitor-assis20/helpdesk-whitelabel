/**
 * Widget de atendimento embutível.
 *
 * A empresa cola no próprio site:
 *   <script src="https://{empresa}.{dominio}/api/widget.js" async></script>
 *
 * Tudo vive dentro de um Shadow DOM (o CSS do site não afeta o widget e vice-versa).
 * Texto vindo de usuários entra sempre por textContent, nunca innerHTML.
 */
import Pusher, { type Channel } from 'pusher-js';

import { ValidationError, WidgetApi } from './api';
import { styles } from './styles';
import type { CustomerTicket, WidgetConfig } from './types';

const CHAT_ICON =
    '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';

type Attributes = Record<string, string | boolean | ((event: Event) => void)>;

/** Cria um elemento; filhos string viram nós de texto (seguro contra XSS). */
function el<K extends keyof HTMLElementTagNameMap>(tag: K, attributes: Attributes = {}, children: (Node | string)[] = []): HTMLElementTagNameMap[K] {
    const node = document.createElement(tag);
    for (const [name, value] of Object.entries(attributes)) {
        if (typeof value === 'function') node.addEventListener(name.slice(2), value);
        else if (value === true) node.setAttribute(name, '');
        else if (value !== false) node.setAttribute(name, value);
    }
    node.append(...children);
    return node;
}

class HelpdeskWidget {
    private readonly api: WidgetApi;
    private readonly storageKey: string;
    private readonly root: ShadowRoot;
    private readonly launcher: HTMLButtonElement;
    private readonly panel: HTMLDivElement;
    private readonly subtitle = el('small');
    private readonly body = el('div', { class: 'body' });
    private readonly footer = el('div', { class: 'footer' });

    private current: CustomerTicket | null = null;
    private pusher: Pusher | null = null;
    private channel: Channel | null = null;
    private seenMessages = 0;

    constructor(private readonly config: WidgetConfig) {
        this.api = new WidgetApi(config);
        this.storageKey = `helpdesk-widget:${config.tenant.slug}:token`;

        const host = el('div', { id: 'helpdesk-widget' });
        this.root = host.attachShadow({ mode: 'open' });
        host.style.setProperty('--brand', config.color);
        host.style.setProperty('--brand-text', config.textColor);

        this.launcher = el('button', {
            class: 'launcher',
            type: 'button',
            'aria-label': `Falar com ${config.tenant.name}`,
            'aria-expanded': 'false',
            onclick: () => this.toggle(),
        });
        this.launcher.innerHTML = CHAT_ICON; // ícone fixo, sem dado de usuário

        this.panel = el('div', { class: 'panel', role: 'dialog', 'aria-label': `Atendimento ${config.tenant.name}`, hidden: true }, [
            el('div', { class: 'header' }, [
                el('div', {}, [el('strong', {}, [config.tenant.name]), this.subtitle]),
                el('button', { class: 'icon-button', type: 'button', 'aria-label': 'Fechar', onclick: () => this.toggle(false) }, ['✕']),
            ]),
            this.body,
            this.footer,
        ]);

        this.root.append(el('style', {}, [styles]), this.panel, this.launcher);
        document.body.append(host);
        document.addEventListener('keydown', (event) => event.key === 'Escape' && this.isOpen() && this.toggle(false));
    }

    private isOpen(): boolean {
        return !this.panel.hidden;
    }

    private toggle(open = !this.isOpen()): void {
        this.panel.hidden = !open;
        this.launcher.setAttribute('aria-expanded', String(open));
        this.launcher.style.removeProperty('box-shadow');

        if (open && !this.current) void this.start();
        if (open) this.seenMessages = this.current?.messages.length ?? 0;
    }

    /** Retoma a conversa salva neste site, se houver; senão mostra o formulário. */
    private async start(): Promise<void> {
        const token = localStorage.getItem(this.storageKey);
        if (!token) return this.renderForm();

        this.renderStatus('Carregando sua conversa…');
        try {
            this.show(await this.api.show(token));
        } catch {
            localStorage.removeItem(this.storageKey);
            this.renderForm();
        }
    }

    private renderStatus(text: string): void {
        this.body.replaceChildren(el('p', { class: 'status' }, [text]));
        this.footer.replaceChildren();
    }

    private renderForm(): void {
        this.current = null;
        this.subtitle.textContent = 'Respondemos por aqui e por e-mail';
        const field = (label: string, input: HTMLInputElement | HTMLTextAreaElement) => el('label', {}, [label, input]);
        const name = el('input', { name: 'name', autocomplete: 'name', required: true });
        const email = el('input', { name: 'email', type: 'email', autocomplete: 'email', required: true });
        const subject = el('input', { name: 'subject', maxlength: '200', required: true });
        const body = el('textarea', { name: 'body', required: true });
        const error = el('p', { class: 'error', role: 'alert' });
        const submit = el('button', { class: 'primary', type: 'submit' }, ['Enviar']);

        const form = el(
            'form',
            {
                onsubmit: async (event: Event) => {
                    event.preventDefault();
                    submit.disabled = true;
                    error.textContent = '';
                    try {
                        const ticket = await this.api.open({ name: name.value, email: email.value, subject: subject.value, body: body.value });
                        localStorage.setItem(this.storageKey, ticket.ticket.token);
                        this.show(ticket);
                    } catch (failure) {
                        error.textContent = failure instanceof Error ? failure.message : 'Algo deu errado.';
                        submit.disabled = false;
                    }
                },
            },
            [
                el('p', { class: 'intro' }, ['Como podemos ajudar? Conte o que aconteceu.']),
                field('Nome', name),
                field('E-mail', email),
                field('Assunto', subject),
                field('Mensagem', body),
                error,
                submit,
            ],
        );
        form.style.display = 'grid';
        form.style.gap = '10px';

        this.body.replaceChildren(form);
        this.footer.replaceChildren();
        name.focus();
    }

    private show(data: CustomerTicket): void {
        const isNewMessage = this.current && data.messages.length > this.current.messages.length;
        this.current = data;
        this.subtitle.textContent = `Chamado #${data.ticket.number} · ${data.ticket.status_label}`;

        this.body.replaceChildren(
            ...data.messages.map((message) =>
                el('div', { class: `bubble ${message.from_customer ? 'mine' : 'theirs'}` }, [
                    el('small', {}, [message.from_customer ? 'Você' : message.author]),
                    message.body,
                ]),
            ),
        );
        this.body.scrollTop = this.body.scrollHeight;
        this.renderReply(data);
        this.subscribe(data);

        // Resposta nova com o painel fechado: destaca o botão.
        if (isNewMessage && !this.isOpen()) this.launcher.style.boxShadow = `0 0 0 4px #fff, 0 0 0 7px ${this.config.color}`;
        if (this.isOpen()) this.seenMessages = data.messages.length;
    }

    private renderReply(data: CustomerTicket): void {
        const restart = el('button', { class: 'link', type: 'button', onclick: () => this.restart() }, ['Abrir um novo chamado']);

        if (!data.ticket.accepts_messages) {
            this.footer.replaceChildren(el('p', { class: 'status' }, ['Este chamado foi encerrado. ', restart]));
            return;
        }

        const input = el('textarea', { 'aria-label': 'Sua resposta', placeholder: 'Escreva sua resposta…', required: true });
        const send = el('button', { class: 'primary', type: 'submit' }, ['Enviar']);
        const error = el('p', { class: 'error', role: 'alert' });

        this.footer.replaceChildren(
            el(
                'form',
                {
                    class: 'reply',
                    onsubmit: async (event: Event) => {
                        event.preventDefault();
                        send.disabled = true;
                        try {
                            this.show(await this.api.reply(data.ticket.token, input.value));
                        } catch (failure) {
                            error.textContent = failure instanceof ValidationError || failure instanceof Error ? failure.message : 'Algo deu errado.';
                            send.disabled = false;
                        }
                    },
                },
                [input, send],
            ),
            error,
            restart,
        );
    }

    private restart(): void {
        localStorage.removeItem(this.storageKey);
        this.unsubscribe();
        this.renderForm();
    }

    /** Tempo real: canal privado do chamado, autorizado pelo token (sem cookies). */
    private subscribe(data: CustomerTicket): void {
        const channelName = `private-portal.tickets.${data.ticket.id}`;
        if (this.channel?.name === channelName) return;
        this.unsubscribe();

        const { reverb } = this.config;
        this.pusher ??= new Pusher(reverb.key, {
            cluster: 'mt1',
            wsHost: reverb.host,
            wsPort: reverb.port,
            wssPort: reverb.port,
            forceTLS: reverb.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            channelAuthorization: {
                endpoint: `${this.config.apiUrl}/broadcasting/auth`,
                transport: 'ajax',
                headersProvider: () => ({ 'X-Ticket-Token': this.current?.ticket.token ?? '' }),
            },
        });

        this.channel = this.pusher.subscribe(channelName);
        // O evento não traz conteúdo: busca a conversa de novo pela API.
        this.channel.bind('ticket.changed', async () => {
            if (this.current) this.show(await this.api.show(this.current.ticket.token));
        });
    }

    private unsubscribe(): void {
        if (this.channel && this.pusher) this.pusher.unsubscribe(this.channel.name);
        this.channel = null;
    }
}

// Carrega uma vez só, mesmo que o script esteja duas vezes na página.
if (window.__HELPDESK_WIDGET__ && !window.__HELPDESK_WIDGET_LOADED__) {
    window.__HELPDESK_WIDGET_LOADED__ = true;
    const config = window.__HELPDESK_WIDGET__;
    const boot = () => new HelpdeskWidget(config);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}
