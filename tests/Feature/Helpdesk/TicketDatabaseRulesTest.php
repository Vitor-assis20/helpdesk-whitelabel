<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\MessageKind;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * Regras garantidas pelo próprio Postgres, independentes do código da aplicação.
 */
class TicketDatabaseRulesTest extends HelpdeskTestCase
{
    private const FOREIGN_KEY_VIOLATION = '23503';

    private const UNIQUE_VIOLATION = '23505';

    private const CHECK_VIOLATION = '23514';

    public function test_ticket_numbers_are_unique_per_business()
    {
        $ticket = Ticket::factory()->for($this->tenant)->create();

        $this->assertRejectedByDatabase(self::UNIQUE_VIOLATION, fn () => Ticket::factory()->for($this->tenant)->create(['number' => $ticket->number]));

        // Outra empresa pode ter um chamado com o mesmo número.
        Ticket::factory()->for(Tenant::factory())->create(['number' => $ticket->number]);
        $this->assertSame(2, Ticket::withoutGlobalScopes()->count());
    }

    public function test_the_assignee_must_be_a_member_of_the_business()
    {
        $outsider = User::factory()->create();

        $this->assertRejectedByDatabase(self::FOREIGN_KEY_VIOLATION, fn () => Ticket::factory()->for($this->tenant)->create(['assignee_id' => $outsider->id]));
    }

    public function test_leaving_the_team_unassigns_only_the_assignee_column()
    {
        $ticket = Ticket::factory()->for($this->tenant)->create(['assignee_id' => $this->agent->id]);

        $this->tenant->users()->detach($this->agent);

        $ticket->refresh();
        $this->assertNull($ticket->assignee_id);
        $this->assertSame($this->tenant->id, $ticket->tenant_id);
    }

    public function test_a_ticket_cannot_reference_a_customer_of_another_business()
    {
        $foreign = Customer::factory()->create();

        $this->assertRejectedByDatabase(self::FOREIGN_KEY_VIOLATION, fn () => Ticket::factory()->for($this->tenant)->create(['customer_id' => $foreign->id]));
    }

    public function test_customers_never_write_internal_notes()
    {
        $ticket = Ticket::factory()->for($this->tenant)->create(['customer_id' => $this->customer->id]);

        $this->assertRejectedByDatabase(self::CHECK_VIOLATION, fn () => TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'customer_id' => $this->customer->id,
            'kind' => MessageKind::Note,
        ]));
    }

    public function test_a_message_has_at_most_one_author()
    {
        $ticket = Ticket::factory()->for($this->tenant)->create(['customer_id' => $this->customer->id]);

        $this->assertRejectedByDatabase(self::CHECK_VIOLATION, fn () => TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->agent->id,
        ]));
    }

    public function test_ticket_numbers_come_from_an_atomic_counter()
    {
        $numbers = collect(range(1, 3))->map(fn () => DB::transaction(fn () => $this->tenant->nextTicketNumber()));

        $this->assertSame([1, 2, 3], $numbers->all());
        $this->assertSame(1, Tenant::factory()->create()->nextTicketNumber());
    }

    public function test_queries_only_see_the_current_business()
    {
        Ticket::factory()->count(2)->for($this->tenant)->create();
        Ticket::factory()->count(3)->for(Tenant::factory())->create();

        $this->assertSame(0, Ticket::count(), 'Sem empresa no contexto, nada é visível.');
        $this->assertSame(2, app(CurrentTenant::class)->run($this->tenant, fn () => Ticket::count()));
    }
}
