<?php

namespace Database\Factories;

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cria o chamado direto, sem mensagens nem histórico. Para o fluxo
 * completo, use App\Helpdesk\OpenTicket.
 *
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // Mesmo contador usado pela aplicação, para não colidir com chamados reais.
            'number' => fn (array $attributes) => Tenant::findOrFail($attributes['tenant_id'])->nextTicketNumber(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['tenant_id' => $attributes['tenant_id']]),
            'subject' => fake()->sentence(5),
            'status' => TicketStatus::New,
            'priority' => TicketPriority::Normal,
            'channel' => TicketChannel::Portal,
            'last_activity_at' => now(),
        ];
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
