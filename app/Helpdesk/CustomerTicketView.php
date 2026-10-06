<?php

namespace App\Helpdesk;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketMessage;

/**
 * O chamado como o cliente o vê: usado pelo portal e pela API do widget.
 * Só respostas públicas; nota interna nunca sai daqui.
 */
final class CustomerTicketView
{
    /**
     * @return array{ticket: array<string, mixed>, messages: list<array<string, mixed>>}
     */
    public static function of(Ticket $ticket): array
    {
        $ticket->loadMissing('customer', 'tenant');

        return [
            'ticket' => [
                'id' => $ticket->id,
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'accepts_messages' => $ticket->status !== TicketStatus::Closed,
                'token' => $ticket->access_token,
            ],
            'messages' => $ticket->messages()->public()->with('user:id,name')->get()
                ->map(fn (TicketMessage $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'author' => $message->customer_id ? $ticket->customer->name : ($message->user?->name ?? $ticket->tenant->name),
                    'from_customer' => $message->customer_id !== null,
                    'created_at' => $message->created_at->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
