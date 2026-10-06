import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler, useEffect, useRef } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { usePortalUpdates } from '@/hooks/use-portal-updates';
import TenantLayout from '@/layouts/tenant-layout';
import { formatDateTime, timeAgo } from '@/lib/time';
import { cn } from '@/lib/utils';
import { type PortalMessage, type PortalTicket } from '@/types/portal';

export default function PortalTicketPage({ ticket, messages }: { ticket: PortalTicket; messages: PortalMessage[] }) {
    const form = useForm({ body: '' });
    const end = useRef<HTMLDivElement>(null);

    // A equipe respondeu ou mudou o status: recarrega a conversa, sem perder o que o cliente está digitando.
    usePortalUpdates(ticket, () => router.reload({ only: ['ticket', 'messages'] }));

    useEffect(() => end.current?.scrollIntoView({ behavior: 'smooth' }), [messages.length]);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('portal.tickets.reply', { ticket: ticket.token }), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <TenantLayout>
            <Head title={`Chamado #${ticket.number}`} />

            <div className="space-y-6">
                <div>
                    <p className="text-muted-foreground text-sm">Chamado #{ticket.number}</p>
                    <h1 className="text-2xl font-semibold">{ticket.subject}</h1>
                    <span className="bg-muted mt-2 inline-block rounded-full px-2 py-0.5 text-xs font-medium">{ticket.status_label}</span>
                </div>

                <div className="flex flex-col gap-3">
                    {messages.map((message) => (
                        <div key={message.id} className={cn('flex', message.from_customer ? 'justify-end' : 'justify-start')}>
                            <div
                                className={cn(
                                    'max-w-[85%] rounded-xl border px-4 py-3',
                                    message.from_customer ? 'bg-primary text-primary-foreground border-primary' : 'bg-background',
                                )}
                            >
                                <div className={cn('mb-1 text-xs', message.from_customer ? 'opacity-80' : 'text-muted-foreground')}>
                                    <span className="font-medium">{message.from_customer ? 'Você' : message.author}</span> ·{' '}
                                    <span title={formatDateTime(message.created_at)}>{timeAgo(message.created_at)}</span>
                                </div>
                                <p className="text-sm whitespace-pre-wrap">{message.body}</p>
                            </div>
                        </div>
                    ))}
                    <div ref={end} />
                </div>

                {ticket.accepts_messages ? (
                    <form onSubmit={submit} className="bg-background space-y-3 rounded-lg border p-3">
                        <textarea
                            aria-label="Mensagem"
                            className="placeholder:text-muted-foreground min-h-24 w-full resize-y bg-transparent text-sm outline-none"
                            placeholder="Escreva sua resposta…"
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        <InputError message={form.errors.body} />
                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing || !form.data.body.trim()}>
                                {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                                Enviar resposta
                            </Button>
                        </div>
                    </form>
                ) : (
                    <p className="text-muted-foreground rounded-lg border p-4 text-sm">
                        Este chamado foi encerrado. Se precisar de algo, abra um novo chamado.
                    </p>
                )}
            </div>
        </TenantLayout>
    );
}
