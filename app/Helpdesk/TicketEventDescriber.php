<?php

namespace App\Helpdesk;

use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Models\TicketEvent;
use Illuminate\Support\Collection;

/**
 * Frase legível para cada entrada do histórico do chamado.
 */
class TicketEventDescriber
{
    /**
     * @param  Collection<int, string>  $names  Nomes da equipe por id.
     */
    public function describe(TicketEvent $event, Collection $names): string
    {
        $actor = $event->user_id ? $names->get($event->user_id, 'Ex-membro da equipe') : null;

        return match ($event->type) {
            'created' => 'Chamado aberto pelo cliente'.match (TicketChannel::tryFrom($event->data['channel'] ?? '')) {
                TicketChannel::Widget => ' (widget do site)',
                TicketChannel::Portal => ' (portal)',
                TicketChannel::Email => ' (e-mail)',
                default => '',
            },
            'assigned' => match (true) {
                $event->data['to'] === null => "{$actor} removeu o responsável",
                $event->data['to'] === $event->user_id => "{$actor} assumiu o chamado",
                default => "{$actor} atribuiu a ".$names->get($event->data['to'], 'Ex-membro da equipe'),
            },
            'status_changed' => sprintf(
                '%sStatus: %s',
                match ($event->data['reason'] ?? null) {
                    'customer_reply' => 'O cliente respondeu · ',
                    'agent_reply', 'assigned' => '',
                    default => $actor ? "{$actor} · " : '',
                },
                TicketStatus::from($event->data['to'])->label(),
            ),
            default => $event->type,
        };
    }
}
