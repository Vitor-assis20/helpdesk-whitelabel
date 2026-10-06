<?php

namespace App\Helpdesk;

use App\Models\Ticket;
use Illuminate\Support\Str;

/**
 * Chamado já formatado para os e-mails ao cliente.
 *
 * Só dados prontos, sem models: as notificações vão para a fila, e o worker
 * não tem empresa no contexto para recarregar models com escopo.
 */
final readonly class TicketMailSummary
{
    public function __construct(
        public string $tenantName,
        public ?string $brandColor,
        public int $number,
        public string $subject,
        public string $customerName,
        public string $customerEmail,
        public string $ticketUrl,
        public string $portalUrl,
    ) {}

    public static function of(Ticket $ticket): self
    {
        $tenant = $ticket->tenant;
        $customer = $ticket->customer()->withoutGlobalScopes()->firstOrFail();

        return new self(
            tenantName: $tenant->name,
            brandColor: $tenant->publicBranding()['primary_color'],
            number: $ticket->number,
            subject: $ticket->subject,
            customerName: $customer->name,
            customerEmail: $customer->email,
            ticketUrl: route('portal.tickets.show', ['tenant' => $tenant->slug, 'ticket' => $ticket->access_token]),
            portalUrl: route('portal.home', ['tenant' => $tenant->slug]),
        );
    }

    public function customerFirstName(): string
    {
        return Str::before($this->customerName, ' ');
    }
}
