<?php

namespace App\Helpdesk;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Notifications\AgentReplied;
use App\Notifications\TicketNotification;
use App\Notifications\TicketReceived;
use App\Notifications\TicketResolved;
use Illuminate\Support\Facades\Notification;

/**
 * E-mails ao cliente. Chamar depois do commit: e-mail enviado não volta atrás.
 * Nota interna nunca gera e-mail.
 */
class CustomerNotifier
{
    public function received(Ticket $ticket): void
    {
        $this->send(new TicketReceived(TicketMailSummary::of($ticket)));
    }

    public function agentReplied(Ticket $ticket, TicketMessage $reply): void
    {
        if ($reply->isNote()) {
            return;
        }

        $this->send(new AgentReplied(TicketMailSummary::of($ticket), $reply->user()->value('name') ?? 'Nossa equipe', $reply->body));
    }

    public function resolved(Ticket $ticket): void
    {
        $this->send(new TicketResolved(TicketMailSummary::of($ticket)));
    }

    private function send(TicketNotification $notification): void
    {
        // Destinatário avulso (e-mail + nome), não o model: a fila não o recarregaria sem empresa.
        Notification::route('mail', [$notification->summary->customerEmail => $notification->summary->customerName])->notify($notification);
    }
}
