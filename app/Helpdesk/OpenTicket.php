<?php

namespace App\Helpdesk;

use App\Enums\MessageKind;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Helpdesk\Sla\SlaClock;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\DB;

class OpenTicket
{
    public function __construct(
        private readonly TicketLifecycle $lifecycle,
        private readonly SlaClock $sla,
    ) {}

    /**
     * Abre um chamado em nome do cliente, com a primeira mensagem dele.
     */
    public function handle(
        Customer $customer,
        string $subject,
        string $body,
        TicketChannel $channel,
        TicketPriority $priority = TicketPriority::Normal,
    ): Ticket {
        $tenant = $customer->tenant;

        return DB::transaction(function () use ($tenant, $customer, $subject, $body, $channel, $priority) {
            $ticket = new Ticket([
                'customer_id' => $customer->id,
                'subject' => $subject,
                'priority' => $priority,
                'channel' => $channel,
            ]);
            $ticket->forceFill([
                'tenant_id' => $tenant->id,
                'number' => $tenant->nextTicketNumber(),
                'status' => TicketStatus::New,
                'last_activity_at' => now(),
            ])->save();

            // Prazos de primeira resposta e de resolução, em horário comercial.
            $this->sla->applyTo($ticket);
            $ticket->save();

            $message = new TicketMessage([
                'ticket_id' => $ticket->id,
                'customer_id' => $customer->id,
                'kind' => MessageKind::Reply,
                'body' => $body,
            ]);
            $message->tenant_id = $tenant->id;
            $message->save();

            $this->lifecycle->record($ticket, 'created', null, ['channel' => $channel->value]);
            TicketChanged::dispatch($ticket->tenant_id, $ticket->id, $ticket->number, TicketChanged::CREATED);

            return $ticket;
        });
    }
}
