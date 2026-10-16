<?php

namespace App\Helpdesk;

use App\Enums\TicketStatus;
use App\Helpdesk\Sla\SlaClock;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;

/**
 * Aplica mudanças de status e grava o histórico. Usado pelas ações de
 * domínio; as regras de quem pode mudar o quê ficam nelas.
 */
class TicketLifecycle
{
    public function __construct(private readonly SlaClock $sla) {}

    /**
     * @param  string  $reason  Por que mudou: manual, agent_reply, customer_reply, assigned…
     */
    public function moveTo(Ticket $ticket, TicketStatus $status, ?User $actor, string $reason): void
    {
        $from = $ticket->status;

        if ($from === $status) {
            return;
        }

        $ticket->status = $status;
        $ticket->resolved_at = match (true) {
            $status === TicketStatus::Resolved => now(),
            $status === TicketStatus::Closed => $ticket->resolved_at,
            default => null, // reaberto: deixa de contar como resolvido
        };

        if ($status === TicketStatus::Closed) {
            $ticket->closed_at = now();
        }

        // Aguardando o cliente, o prazo de resolução não corre.
        if ($status === TicketStatus::Pending) {
            $this->sla->pause($ticket);
        } elseif ($from === TicketStatus::Pending) {
            $this->sla->resume($ticket);
        }

        $ticket->save();

        $this->record($ticket, 'status_changed', $actor, [
            'from' => $from->value,
            'to' => $status->value,
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Ticket $ticket, string $type, ?User $actor, array $data = []): TicketEvent
    {
        $event = new TicketEvent(['ticket_id' => $ticket->id, 'user_id' => $actor?->id, 'type' => $type, 'data' => $data]);
        $event->tenant_id = $ticket->tenant_id;
        $event->save();

        return $event;
    }
}
