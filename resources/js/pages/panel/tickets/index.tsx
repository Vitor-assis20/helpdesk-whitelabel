import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useEffect, useRef, useState } from 'react';

import { PriorityLabel, StatusBadge } from '@/components/ticket-badges';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { timeAgo } from '@/lib/time';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { type Paginated, type TicketChangedPayload, type TicketListItem } from '@/types/panel';

type View = 'open' | 'mine' | 'unassigned' | 'done';

interface InboxProps {
    view: View;
    counts: Record<View, number>;
    tickets: Paginated<TicketListItem>;
}

const VIEWS: { value: View; label: string }[] = [
    { value: 'open', label: 'Em aberto' },
    { value: 'mine', label: 'Meus' },
    { value: 'unassigned', label: 'Sem responsável' },
    { value: 'done', label: 'Resolvidos' },
];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chamados', href: '/painel/chamados' }];

/** Destaca por alguns segundos os chamados que acabaram de mudar. */
function useRecentlyChanged(durationMs = 4000) {
    const [ids, setIds] = useState<Set<number>>(new Set());
    const timers = useRef(new Map<number, ReturnType<typeof setTimeout>>());

    useEffect(() => () => timers.current.forEach(clearTimeout), []);

    const mark = (id: number) => {
        clearTimeout(timers.current.get(id));
        setIds((current) => new Set(current).add(id));
        timers.current.set(
            id,
            setTimeout(() => {
                setIds((current) => {
                    const next = new Set(current);
                    next.delete(id);
                    return next;
                });
            }, durationMs),
        );
    };

    return { ids, mark };
}

export default function Inbox({ view, counts, tickets }: InboxProps) {
    const { panel } = usePage<SharedData>().props;
    const recentlyChanged = useRecentlyChanged();
    // Re-renderiza a cada minuto para manter os "há X minutos" corretos.
    const [, setTick] = useState(0);
    useEffect(() => {
        const interval = setInterval(() => setTick((tick) => tick + 1), 60_000);
        return () => clearInterval(interval);
    }, []);

    // Tempo real: o evento só diz que algo mudou; a lista vem do servidor, já filtrada.
    useEcho<TicketChangedPayload>(`tenants.${panel!.current.id}.tickets`, '.ticket.changed', (payload) => {
        recentlyChanged.mark(payload.ticket_id);
        router.reload({ only: ['tickets', 'counts'] });
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Chamados" />

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap gap-2">
                    {VIEWS.map((item) => (
                        <Button key={item.value} variant={view === item.value ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={route('panel.tickets.index', item.value === 'open' ? {} : { view: item.value })} preserveScroll>
                                {item.label}
                                <span className="ml-1 opacity-70">{counts[item.value]}</span>
                            </Link>
                        </Button>
                    ))}
                </div>

                {tickets.data.length === 0 ? (
                    <p className="text-muted-foreground py-8 text-center">Nenhum chamado por aqui.</p>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {tickets.data.map((ticket) => (
                            <li key={ticket.id}>
                                <Link
                                    href={route('panel.tickets.show', { ticket: ticket.number })}
                                    className={cn(
                                        'hover:bg-muted/50 grid gap-1 p-4 transition-colors duration-700 sm:grid-cols-[4rem_1fr_auto] sm:items-center sm:gap-4',
                                        recentlyChanged.ids.has(ticket.id) && 'bg-amber-50 dark:bg-amber-950/30',
                                    )}
                                >
                                    <span className="text-muted-foreground text-sm tabular-nums">#{ticket.number}</span>
                                    <div className="min-w-0">
                                        <div className="truncate font-medium">{ticket.subject}</div>
                                        <div className="text-muted-foreground flex flex-wrap items-center gap-x-2 text-sm">
                                            <span>{ticket.customer}</span>
                                            <span>·</span>
                                            <PriorityLabel priority={ticket.priority} label={ticket.priority_label} />
                                            <span>·</span>
                                            <span>{ticket.assignee ?? 'Sem responsável'}</span>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3 sm:justify-end">
                                        <StatusBadge status={ticket.status} label={ticket.status_label} />
                                        <span className="text-muted-foreground w-24 text-right text-xs">{timeAgo(ticket.last_activity_at)}</span>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}

                {tickets.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Página {tickets.current_page} de {tickets.last_page}
                        </span>
                        <div className="flex gap-2">
                            {tickets.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={tickets.prev_page_url}>Anterior</Link>
                                </Button>
                            )}
                            {tickets.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={tickets.next_page_url}>Próxima</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
