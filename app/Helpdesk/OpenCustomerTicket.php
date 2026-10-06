<?php

namespace App\Helpdesk;

use App\Enums\TicketChannel;
use App\Models\Customer;
use App\Models\Ticket;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Abertura feita pelo próprio cliente (portal ou widget), na empresa do
 * contexto: identifica o cliente pelo e-mail e abre o chamado.
 */
class OpenCustomerTicket
{
    public function __construct(
        private readonly OpenTicket $open,
        private readonly CurrentTenant $current,
    ) {}

    public function handle(string $name, string $email, string $subject, string $body, TicketChannel $channel): Ticket
    {
        return DB::transaction(function () use ($name, $email, $subject, $body, $channel) {
            // O nome de um cadastro existente não é sobrescrito por quem digitar o mesmo e-mail.
            $customer = Customer::firstOrCreate(['email' => Str::lower($email)], ['name' => $name]);
            $customer->setRelation('tenant', $this->current->get());

            return $this->open->handle($customer, $subject, $body, $channel);
        });
    }
}
