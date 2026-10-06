<?php

namespace App\Helpdesk;

use App\Enums\TicketPriority;
use App\Events\TicketChanged;
use App\Helpdesk\Sla\SlaClock;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChangeTicketPriority
{
    public function __construct(
        private readonly TicketLifecycle $lifecycle,
        private readonly SlaClock $sla,
    ) {}

    /**
     * Muda a prioridade e recalcula os prazos de SLA a partir da abertura.
     *
     * @throws TicketActionFailed
     */
    public function handle(Ticket $ticket, TicketPriority $priority, User $actor): Ticket
    {
        if (! $ticket->tenant->hasMember($actor)) {
            throw TicketActionFailed::notMember();
        }

        if ($ticket->priority === $priority) {
            return $ticket;
        }

        DB::transaction(function () use ($ticket, $priority, $actor) {
            $from = $ticket->priority;
            $ticket->priority = $priority;
            $this->sla->applyTo($ticket);
            $ticket->save();

            $this->lifecycle->record($ticket, 'priority_changed', $actor, ['from' => $from->value, 'to' => $priority->value]);
            TicketChanged::dispatch($ticket->tenant_id, $ticket->id, $ticket->number, TicketChanged::PRIORITY);
        });

        return $ticket;
    }
}
