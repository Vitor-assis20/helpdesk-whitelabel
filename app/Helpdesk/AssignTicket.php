<?php

namespace App\Helpdesk;

use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignTicket
{
    public function __construct(private readonly TicketLifecycle $lifecycle) {}

    /**
     * Define (ou remove, com null) o responsável. Um chamado novo passa a
     * "em atendimento" quando ganha responsável.
     *
     * @throws TicketActionFailed
     */
    public function handle(Ticket $ticket, ?User $assignee, User $actor): Ticket
    {
        $tenant = $ticket->tenant;

        if (! $tenant->hasMember($actor) || ($assignee && ! $tenant->hasMember($assignee))) {
            throw TicketActionFailed::notMember();
        }

        if ($ticket->assignee_id === $assignee?->id) {
            return $ticket;
        }

        DB::transaction(function () use ($ticket, $assignee, $actor) {
            $from = $ticket->assignee_id;
            $ticket->assignee_id = $assignee?->id;
            $ticket->save();

            $this->lifecycle->record($ticket, 'assigned', $actor, ['from' => $from, 'to' => $assignee?->id]);

            if ($assignee && $ticket->status === TicketStatus::New) {
                $this->lifecycle->moveTo($ticket, TicketStatus::Open, $actor, 'assigned');
            }

            TicketChanged::dispatch($ticket->tenant_id, $ticket->id, $ticket->number, TicketChanged::ASSIGNMENT);
        });

        return $ticket;
    }
}
