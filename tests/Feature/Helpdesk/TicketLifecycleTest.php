<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\MessageKind;
use App\Enums\TicketStatus;
use App\Helpdesk\AssignTicket;
use App\Helpdesk\ChangeTicketStatus;
use App\Helpdesk\ReplyToTicket;
use App\Helpdesk\TicketActionFailed;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Carbon\CarbonImmutable;

class TicketLifecycleTest extends HelpdeskTestCase
{
    public function test_opening_a_ticket_numbers_it_and_records_the_first_message()
    {
        $first = $this->openTicket();
        $second = $this->openTicket(subject: 'Troca de tamanho');
        $elsewhere = $this->openTicket(Customer::factory()->for(Tenant::factory())->create());

        $this->assertSame([1, 2], [$first->number, $second->number]);
        $this->assertSame(1, $elsewhere->number, 'A numeração é independente por empresa.');
        $this->assertSame(TicketStatus::New, $first->status);

        $message = $first->messages()->withoutGlobalScopes()->sole();
        $this->assertSame($this->customer->id, $message->customer_id);
        $this->assertSame(MessageKind::Reply, $message->kind);
        $this->assertSame(['created'], $this->eventTypes($first));
    }

    public function test_an_agent_reply_marks_the_first_response_and_waits_for_the_customer()
    {
        $ticket = $this->openTicket();
        $this->travel(30)->minutes();

        $this->reply()->asAgent($ticket, $this->agent, 'Vou verificar com a transportadora.');
        $firstResponse = $ticket->first_response_at;
        $this->travel(1)->hours();
        $this->reply()->asAgent($ticket, $this->agent, 'Atualização: saiu para entrega.');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Pending, $ticket->status);
        $this->assertTrue($ticket->first_response_at->equalTo($firstResponse), 'A primeira resposta não muda depois.');
        $this->assertTrue($ticket->last_activity_at->equalTo(now()->startOfSecond()), 'O banco guarda segundos, sem microssegundos.');
    }

    public function test_an_internal_note_changes_nothing_for_the_customer()
    {
        $ticket = $this->openTicket();

        $note = $this->reply()->asAgent($ticket, $this->agent, 'Cliente VIP, priorizar.', internal: true);

        $ticket->refresh();
        $this->assertTrue($note->isNote());
        $this->assertSame(TicketStatus::New, $ticket->status);
        $this->assertNull($ticket->first_response_at, 'Nota interna não conta como resposta ao cliente.');
    }

    public function test_a_customer_reply_reopens_a_waiting_or_resolved_ticket()
    {
        $ticket = $this->openTicket();
        $this->reply()->asAgent($ticket, $this->agent, 'Pode confirmar o número do pedido?');

        $this->reply()->asCustomer($ticket, $this->customer, 'É o 12345.');
        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);

        $this->changeStatus()->handle($ticket, TicketStatus::Resolved, $this->agent);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $this->reply()->asCustomer($ticket, $this->customer, 'Na verdade ainda não chegou.');
        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->resolved_at, 'Reaberto deixa de contar como resolvido.');
    }

    public function test_a_closed_ticket_accepts_no_more_messages()
    {
        $ticket = $this->openTicket();
        $this->changeStatus()->handle($ticket, TicketStatus::Closed, $this->agent);

        $this->expectExceptionObject(TicketActionFailed::closed());

        $this->reply()->asCustomer($ticket, $this->customer, 'Oi?');
    }

    public function test_only_the_team_and_the_ticket_owner_can_reply()
    {
        $ticket = $this->openTicket();

        $this->assertFails(TicketActionFailed::notMember(), fn () => $this->reply()->asAgent($ticket, User::factory()->create(), 'Olá'));
        $this->assertFails(TicketActionFailed::notOwner(), fn () => $this->reply()->asCustomer($ticket, Customer::factory()->for($this->tenant)->create(), 'Olá'));
    }

    public function test_manual_status_changes_follow_the_allowed_transitions()
    {
        $ticket = $this->openTicket();
        $this->changeStatus()->handle($ticket, TicketStatus::Resolved, $this->agent);
        $this->changeStatus()->handle($ticket, TicketStatus::Closed, $this->agent);

        $this->assertNotNull($ticket->fresh()->closed_at);
        $this->assertFails(
            TicketActionFailed::invalidTransition(TicketStatus::Closed, TicketStatus::Open),
            fn () => $this->changeStatus()->handle($ticket, TicketStatus::Open, $this->agent),
        );
    }

    public function test_assigning_a_new_ticket_puts_it_in_progress()
    {
        $ticket = $this->openTicket();

        app(AssignTicket::class)->handle($ticket, $this->agent, $this->owner);

        $ticket->refresh();
        $this->assertSame($this->agent->id, $ticket->assignee_id);
        $this->assertSame(TicketStatus::Open, $ticket->status);
    }

    public function test_cannot_assign_someone_outside_the_team()
    {
        $ticket = $this->openTicket();

        $this->assertFails(TicketActionFailed::notMember(), fn () => app(AssignTicket::class)->handle($ticket, User::factory()->create(), $this->owner));
        $this->assertNull($ticket->fresh()->assignee_id);
    }

    public function test_the_history_tells_what_happened_and_who_did_it()
    {
        $ticket = $this->openTicket();
        app(AssignTicket::class)->handle($ticket, $this->agent, $this->owner);
        $this->reply()->asAgent($ticket, $this->agent, 'Verificando.');
        $this->reply()->asCustomer($ticket, $this->customer, 'Obrigada!');
        $this->changeStatus()->handle($ticket, TicketStatus::Resolved, $this->agent);

        $events = TicketEvent::withoutGlobalScopes()->where('ticket_id', $ticket->id)->orderBy('id')->get();

        $this->assertSame(
            [
                ['created', null, null],
                ['assigned', $this->owner->id, null],
                ['status_changed', $this->owner->id, 'new→open (assigned)'],
                ['status_changed', $this->agent->id, 'open→pending (agent_reply)'],
                ['status_changed', null, 'pending→open (customer_reply)'],
                ['status_changed', $this->agent->id, 'open→resolved (manual)'],
            ],
            $events->map(fn (TicketEvent $event) => [
                $event->type,
                $event->user_id,
                $event->type === 'status_changed' ? "{$event->data['from']}→{$event->data['to']} ({$event->data['reason']})" : null,
            ])->all(),
        );
        $this->assertInstanceOf(CarbonImmutable::class, $events->first()->created_at);
    }

    private function reply(): ReplyToTicket
    {
        return app(ReplyToTicket::class);
    }

    private function changeStatus(): ChangeTicketStatus
    {
        return app(ChangeTicketStatus::class);
    }

    /**
     * @return list<string>
     */
    private function eventTypes(Ticket $ticket): array
    {
        return TicketEvent::withoutGlobalScopes()->where('ticket_id', $ticket->id)->orderBy('id')->pluck('type')->all();
    }

    private function assertFails(TicketActionFailed $expected, callable $callback): void
    {
        try {
            $callback();
        } catch (TicketActionFailed $e) {
            $this->assertSame($expected->getMessage(), $e->getMessage());

            return;
        }

        $this->fail("Deveria ter falhado com: {$expected->getMessage()}");
    }
}
