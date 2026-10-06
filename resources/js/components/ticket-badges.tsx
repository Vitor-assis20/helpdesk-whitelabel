import { cn } from '@/lib/utils';
import { type TicketPriority, type TicketStatus } from '@/types/panel';

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
