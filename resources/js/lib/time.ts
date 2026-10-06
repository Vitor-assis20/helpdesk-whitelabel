const relative = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });
const dateTime = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

/** "há 5 minutos", "ontem"… */
export function timeAgo(iso: string, now: Date = new Date()): string {
    const seconds = (new Date(iso).getTime() - now.getTime()) / 1000;

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) return relative.format(Math.round(seconds / size), unit);
    }

    return 'agora';
}

/** "06/10/2026, 14:30" */
export function formatDateTime(iso: string): string {
    return dateTime.format(new Date(iso));
}
