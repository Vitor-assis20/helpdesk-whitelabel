import { formatSpan } from '@/lib/time';
import { cn } from '@/lib/utils';
import { type CurrentDeadline, type TicketPriority, type TicketStatus } from '@/types/panel';

const STATUS_STYLES: Record<TicketStatus, string> = {
    new: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
    open: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    resolved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    closed: 'bg-muted text-muted-foreground',
};

const PRIORITY_STYLES: Record<TicketPriority, string> = {
    low: 'text-muted-foreground',
    normal: 'text-foreground',
    high: 'text-orange-600 dark:text-orange-400 font-medium',
    urgent: 'text-red-600 dark:text-red-400 font-semibold',
};

export function StatusBadge({ status, label }: { status: TicketStatus; label: string }) {
    return <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap', STATUS_STYLES[status])}>{label}</span>;
}

export function PriorityLabel({ priority, label }: { priority: TicketPriority; label: string }) {
    return <span className={cn('text-xs', PRIORITY_STYLES[priority])}>{label}</span>;
}

const AT_RISK_MS = 60 * 60_000;

/**
 * Prazo de SLA em vigor: "Responder em 25 min", "Resolver em 2 dias",
 * "Resposta atrasada 1 h". Âmbar na última hora; vermelho quando estourou.
 */
export function SlaBadge({ sla, now = Date.now() }: { sla: CurrentDeadline | null; now?: number }) {
    if (!sla?.due_at) return null;

    if (sla.paused) {
        return (
            <span
                className="text-muted-foreground rounded-full border border-dashed px-2 py-0.5 text-xs font-medium whitespace-nowrap"
                title="Aguardando o cliente: o prazo de resolução está pausado"
            >
                SLA pausado
            </span>
        );
    }

    const remaining = new Date(sla.due_at).getTime() - now;
    const breached = sla.breached || remaining <= 0;
    const verb = sla.target === 'first_response' ? 'Responder' : 'Resolver';
    const noun = sla.target === 'first_response' ? 'Resposta' : 'Resolução';

    return (
        <span
            className={cn(
                'rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                breached && 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
                !breached && remaining <= AT_RISK_MS && 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
                !breached && remaining > AT_RISK_MS && 'text-muted-foreground border',
            )}
            title={`Prazo: ${new Date(sla.due_at).toLocaleString('pt-BR')}`}
        >
            {breached ? `${noun} atrasada ${formatSpan(remaining)}` : `${verb} em ${formatSpan(remaining)}`}
        </span>
    );
}
