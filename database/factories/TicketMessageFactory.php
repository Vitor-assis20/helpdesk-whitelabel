<?php

namespace Database\Factories;

use App\Enums\MessageKind;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'tenant_id' => fn (array $attributes) => Ticket::withoutGlobalScopes()->findOrFail($attributes['ticket_id'])->tenant_id,
            'customer_id' => fn (array $attributes) => Ticket::withoutGlobalScopes()->findOrFail($attributes['ticket_id'])->customer_id,
            'kind' => MessageKind::Reply,
            'body' => fake()->paragraph(),
        ];
    }
}
