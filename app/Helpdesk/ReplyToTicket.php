<?php

namespace App\Helpdesk;

use App\Enums\MessageKind;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReplyToTicket
{
    public function __construct(private readonly TicketLifecycle $lifecycle) {}

    /**
     * Resposta da equipe. Pública: marca a primeira resposta (base do SLA) e
     * passa o chamado a aguardar o cliente. Nota interna: só registra.
     *
     * @throws TicketActionFailed
     */
    public function asAgent(Ticket $ticket, User $agent, string $body, bool $internal = false): TicketMessage
    {
        if (! $ticket->tenant->hasMember($agent)) {
            throw TicketActionFailed::notMember();
        }

        $this->ensureOpen($ticket);

        return DB::transaction(function () use ($ticket, $agent, $body, $internal) {
            $message = $this->addMessage($ticket, $internal ? MessageKind::Note : MessageKind::Reply, $body, userId: $agent->id);

            if (! $internal) {
                $ticket->first_response_at ??= now();
                $this->lifecycle->moveTo($ticket, TicketStatus::Pending, $agent, 'agent_reply');
            }

            $this->touchActivity($ticket);

            return $message;
        });
    }

    /**
     * Resposta do cliente: reabre o chamado se estava aguardando ou resolvido.
     *
     * @throws TicketActionFailed
     */
    public function asCustomer(Ticket $ticket, Customer $customer, string $body): TicketMessage
    {
        if ($ticket->customer_id !== $customer->id) {
            throw TicketActionFailed::notOwner();
        }

        $this->ensureOpen($ticket);

        return DB::transaction(function () use ($ticket, $customer, $body) {
            $message = $this->addMessage($ticket, MessageKind::Reply, $body, customerId: $customer->id);

            if (in_array($ticket->status, [TicketStatus::Pending, TicketStatus::Resolved], true)) {
                $this->lifecycle->moveTo($ticket, TicketStatus::Open, null, 'customer_reply');
            }

            $this->touchActivity($ticket);

            return $message;
        });
    }

    private function ensureOpen(Ticket $ticket): void
    {
        if ($ticket->status === TicketStatus::Closed) {
            throw TicketActionFailed::closed();
        }
    }

    private function addMessage(Ticket $ticket, MessageKind $kind, string $body, ?int $userId = null, ?int $customerId = null): TicketMessage
    {
        $message = new TicketMessage([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'customer_id' => $customerId,
            'kind' => $kind,
            'body' => $body,
        ]);
        $message->tenant_id = $ticket->tenant_id;
        $message->save();

        return $message;
    }

    private function touchActivity(Ticket $ticket): void
    {
        $ticket->last_activity_at = now();
        $ticket->save();

        TicketChanged::dispatch($ticket->tenant_id, $ticket->id, $ticket->number, TicketChanged::MESSAGE);
    }
}
