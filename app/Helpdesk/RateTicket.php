<?php

namespace App\Helpdesk;

use App\Enums\SatisfactionScore;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * O cliente avalia o atendimento de um chamado resolvido (ou encerrado).
 *
 * Avaliar de novo substitui a nota: vale a opinião mais recente. O histórico
 * registra cada avaliação, então a anterior não se perde.
 */
class RateTicket
{
    public function __construct(private readonly TicketLifecycle $lifecycle) {}

    /**
     * @throws TicketActionFailed
     */
    public function handle(Ticket $ticket, SatisfactionScore $score, ?string $comment = null): Ticket
    {
        if (! in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            throw TicketActionFailed::notRatable();
        }

        $comment = trim((string) $comment) ?: null;

        DB::transaction(function () use ($ticket, $score, $comment) {
            $from = $ticket->satisfaction_score;

            $ticket->forceFill([
                'satisfaction_score' => $score,
                'satisfaction_comment' => $comment,
                'satisfaction_rated_at' => now(),
            ])->save();

            $this->lifecycle->record($ticket, 'rated', null, ['score' => $score->value, 'from' => $from?->value]);
            TicketChanged::dispatch($ticket->tenant_id, $ticket->id, $ticket->number, TicketChanged::RATING, visibleToCustomer: true);
        });

        return $ticket;
    }
}
