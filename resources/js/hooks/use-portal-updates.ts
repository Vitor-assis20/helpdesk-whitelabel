import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useEffect, useRef } from 'react';

/**
 * Tempo real no portal do cliente.
 *
 * O cliente não tem login, então o canal privado é autorizado pelo token do
 * link do chamado (cabeçalho X-Ticket-Token), num endpoint próprio do portal.
 * Por isso uma instância de Echo separada da do painel.
 */
export function usePortalUpdates(ticket: { id: number; token: string }, onChange: () => void) {
    const callback = useRef(onChange);
    callback.current = onChange;

    useEffect(() => {
        const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';
        const echo = new Echo({
            broadcaster: 'reverb',
            Pusher,
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/portal/broadcasting/auth',
            auth: {
                headers: {
                    'X-Ticket-Token': ticket.token,
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                },
            },
        });

        echo.private(`portal.tickets.${ticket.id}`).listen('.ticket.changed', () => callback.current());

        return () => echo.disconnect();
    }, [ticket.id, ticket.token]);
}
