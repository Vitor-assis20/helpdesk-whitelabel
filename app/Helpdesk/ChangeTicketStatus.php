<?php

namespace App\Helpdesk;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChangeTicketStatus
{
    public function __construct(private readonly TicketLifecycle $lifecycle) {}

    /**
     * Mudança manual feita pela equipe, dentro das transições permitidas.
     *
     * @throws TicketActionFailed
     */
    public function handle(Ticket $ticket, TicketStatus $status, User $actor): Ticket
    {
        if (! $ticket->tenant->hasMember($actor)) {
            throw TicketActionFailed::notMember();
        }

        if (! $ticket->status->canTransitionTo($status)) {
            throw TicketActionFailed::invalidTransition($ticket->status, $status);
        }

        DB::transaction(fn () => $this->lifecycle->moveTo($ticket, $status, $actor, 'manual'));

        return $ticket;
    }
}
