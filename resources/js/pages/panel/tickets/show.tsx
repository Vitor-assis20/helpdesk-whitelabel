import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEcho, useEchoPresence } from '@laravel/echo-react';
import { Eye, LoaderCircle, Lock } from 'lucide-react';
import { type FormEventHandler, useEffect, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { PriorityLabel, StatusBadge } from '@/components/ticket-badges';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, timeAgo } from '@/lib/time';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { type TicketDetail, type TicketHistoryEntry, type TicketMessage } from '@/types/panel';

interface TicketPageProps {
    ticket: TicketDetail;
    messages: TicketMessage[];
    events: TicketHistoryEntry[];
    team: { id: number; name: string }[];
}

type Viewer = { id: number; name: string };

const UNASSIGNED = 'none';

/**
 * Presença no chamado: quem mais está com ele aberto e quem está digitando.
 * "Digitando" vai por whisper (evento de cliente direto pelo Reverb, sem passar pelo servidor).
 */
function useTicketPresence(ticketId: number, me: Viewer) {
    const [viewers, setViewers] = useState<Viewer[]>([]);
    const [typing, setTyping] = useState<string | null>(null);
    const lastWhisper = useRef(0);
    const { channel } = useEchoPresence(`tickets.${ticketId}.viewers`);

    useEffect(() => {
        const presence = channel();
        if (!presence) return;

        let clear: ReturnType<typeof setTimeout> | undefined;
        presence
            .here((users: Viewer[]) => setViewers(users))
            .joining((user: Viewer) => setViewers((current) => [...current.filter((viewer) => viewer.id !== user.id), user]))
            .leaving((user: Viewer) => setViewers((current) => current.filter((viewer) => viewer.id !== user.id)))
            .listenForWhisper('typing', ({ name }: { name: string }) => {
                setTyping(name);
                clearTimeout(clear);
                clear = setTimeout(() => setTyping(null), 3000);
            });

        return () => clearTimeout(clear);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- uma inscrição por chamado
    }, [ticketId]);

    const notifyTyping = () => {
        // No máximo um aviso a cada 2s.
        if (Date.now() - lastWhisper.current < 2000) return;
        lastWhisper.current = Date.now();
        channel()?.whisper('typing', { name: me.name });
    };

    return { others: viewers.filter((viewer) => viewer.id !== me.id), typing, notifyTyping };
}

function Message({ message }: { message: TicketMessage }) {
    const fromCustomer = message.author_type === 'customer';
    const note = message.kind === 'note';

    return (
        <div className={cn('flex', fromCustomer ? 'justify-start' : 'justify-end')}>
            <div
                className={cn(
                    'max-w-[85%] rounded-xl border px-4 py-3',
                    fromCustomer && 'bg-background',
                    !fromCustomer && !note && 'bg-primary/5 border-primary/20',
                    note && 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40',
                )}
            >
                <div className="text-muted-foreground mb-1 flex items-center gap-2 text-xs">
                    <span className="text-foreground font-medium">{message.author}</span>
                    {note && (
                        <span className="inline-flex items-center gap-1 text-amber-700 dark:text-amber-300">
                            <Lock className="size-3" /> nota interna
                        </span>
                    )}
                    <span title={formatDateTime(message.created_at)}>{timeAgo(message.created_at)}</span>
                </div>
                <p className="text-sm whitespace-pre-wrap">{message.body}</p>
            </div>
        </div>
    );
}

export default function TicketPage({ ticket, messages, events, team }: TicketPageProps) {
    const { auth } = usePage<SharedData>().props;
    const me = { id: auth.user.id, name: auth.user.name };
    const errors = usePage<{ errors: Record<string, string> }>().props.errors;
    const { others, typing, notifyTyping } = useTicketPresence(ticket.id, me);
    const conversationEnd = useRef<HTMLDivElement>(null);

    const form = useForm<{ body: string; internal: boolean }>({ body: '', internal: false });

    // Tempo real: alguém respondeu, mudou status ou responsável → recarrega o chamado.
    useEcho(`tickets.${ticket.id}`, '.ticket.changed', () => {
        router.reload({ only: ['ticket', 'messages', 'events'] });
    });

    useEffect(() => conversationEnd.current?.scrollIntoView({ behavior: 'smooth' }), [messages.length]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('panel.tickets.reply', { ticket: ticket.number }), {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
        });
    };

    const changeStatus = (status: string) =>
        router.patch(route('panel.tickets.status', { ticket: ticket.number }), { status }, { preserveScroll: true });

    const assign = (assigneeId: number | null) =>
        router.patch(route('panel.tickets.assign', { ticket: ticket.number }), { assignee_id: assigneeId }, { preserveScroll: true });

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Chamados', href: '/painel/chamados' },
                { title: `#${ticket.number}`, href: `/painel/chamados/${ticket.number}` },
            ]}
        >
            <Head title={`#${ticket.number} ${ticket.subject}`} />

            <div className="grid flex-1 gap-6 p-4 lg:grid-cols-[1fr_20rem]">
                <section className="flex min-w-0 flex-col gap-4">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold">{ticket.subject}</h1>
                            <StatusBadge status={ticket.status} label={ticket.status_label} />
                        </div>
                        <p className="text-muted-foreground text-sm">
                            #{ticket.number} · aberto {timeAgo(ticket.created_at)} por {ticket.customer.name}
                        </p>
                        {others.length > 0 && (
                            <p className="mt-2 inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-xs text-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
                                <Eye className="size-3" /> {others.map((viewer) => viewer.name).join(', ')} também{' '}
                                {others.length > 1 ? 'estão' : 'está'} vendo este chamado
                            </p>
                        )}
                    </div>

                    <div className="flex flex-col gap-3">
                        {messages.map((message) => (
                            <Message key={message.id} message={message} />
                        ))}
                        <div ref={conversationEnd} />
                    </div>

                    <p className="text-muted-foreground h-4 text-xs">{typing && `${typing} está digitando…`}</p>

                    {ticket.accepts_messages ? (
                        <form
                            onSubmit={submit}
                            className={cn(
                                'space-y-3 rounded-xl border p-3',
                                form.data.internal && 'border-amber-300 bg-amber-50/50 dark:bg-amber-950/20',
                            )}
                        >
                            <textarea
                                aria-label="Mensagem"
                                className="placeholder:text-muted-foreground min-h-28 w-full resize-y bg-transparent text-sm outline-none"
                                placeholder={form.data.internal ? 'Nota visível só para a equipe…' : `Responder para ${ticket.customer.name}…`}
                                value={form.data.body}
                                onChange={(e) => {
                                    form.setData('body', e.target.value);
                                    notifyTyping();
                                }}
                            />
                            <InputError message={form.errors.body} />
                            <div className="flex items-center justify-between gap-3">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.internal}
                                        onChange={(e) => form.setData('internal', e.target.checked)}
                                    />
                                    Nota interna
                                </label>
                                <Button type="submit" disabled={form.processing || !form.data.body.trim()}>
                                    {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                                    {form.data.internal ? 'Salvar nota' : 'Responder'}
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <p className="text-muted-foreground rounded-xl border p-4 text-sm">Chamado encerrado: não aceita novas mensagens.</p>
                    )}
                </section>

                <aside className="space-y-6">
                    <div className="space-y-2">
                        <h2 className="text-sm font-semibold">Status</h2>
                        <div className="flex flex-wrap gap-2">
                            {ticket.transitions.map((transition) => (
                                <Button key={transition.value} size="sm" variant="outline" onClick={() => changeStatus(transition.value)}>
                                    {transition.label}
                                </Button>
                            ))}
                        </div>
                        <InputError message={errors.status} />
                    </div>

                    <div className="space-y-2">
                        <h2 className="text-sm font-semibold">Responsável</h2>
                        <Select
                            value={ticket.assignee_id ? String(ticket.assignee_id) : UNASSIGNED}
                            onValueChange={(value) => assign(value === UNASSIGNED ? null : Number(value))}
                        >
                            <SelectTrigger aria-label="Responsável">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={UNASSIGNED}>Sem responsável</SelectItem>
                                {team.map((member) => (
                                    <SelectItem key={member.id} value={String(member.id)}>
                                        {member.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {ticket.assignee_id !== me.id && (
                            <Button size="sm" variant="link" className="h-auto p-0" onClick={() => assign(me.id)}>
                                Assumir chamado
                            </Button>
                        )}
                        <InputError message={errors.assignee_id} />
                    </div>

                    <div className="space-y-1 text-sm">
                        <h2 className="font-semibold">Cliente</h2>
                        <p>{ticket.customer.name}</p>
                        <p className="text-muted-foreground">{ticket.customer.email}</p>
                        <p className="pt-2">
                            Prioridade: <PriorityLabel priority={ticket.priority} label={ticket.priority_label} />
                        </p>
                        <p className="text-muted-foreground">
                            Primeira resposta: {ticket.first_response_at ? formatDateTime(ticket.first_response_at) : 'ainda não'}
                        </p>
                    </div>

                    <div className="space-y-2">
                        <h2 className="text-sm font-semibold">Histórico</h2>
                        <ol className="space-y-2 border-l pl-4 text-sm">
                            {events.map((event) => (
                                <li key={event.id}>
                                    <p>{event.text}</p>
                                    <p className="text-muted-foreground text-xs" title={formatDateTime(event.created_at)}>
                                        {timeAgo(event.created_at)}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
