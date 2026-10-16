<?php

namespace Tests\Feature\Sla;

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Helpdesk\AssignTicket;
use App\Helpdesk\ChangeTicketPriority;
use App\Helpdesk\ChangeTicketStatus;
use App\Helpdesk\OpenTicket;
use App\Helpdesk\ReplyToTicket;
use App\Helpdesk\TicketEventDescriber;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Notifications\SlaBreached;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Helpdesk\HelpdeskTestCase;

/**
 * Política padrão: normal = 4h úteis para responder e 24h úteis para resolver;
 * expediente padrão de segunda a sexta, 9h às 18h (São Paulo).
 */
class SlaTest extends HelpdeskTestCase
{
    private const TZ = 'America/Sao_Paulo';

    public function test_deadlines_follow_business_hours()
    {
        // Sexta, 17h30.
        $this->travelTo($this->local('2026-10-09 17:30'));

        $ticket = $this->openWithPriority(TicketPriority::Normal);

        $this->assertSame('2026-10-12 12:30', $this->localTime($ticket->first_response_due_at), '30 min na sexta + 3h30 na segunda.');
        $this->assertSame('2026-10-14 14:30', $this->localTime($ticket->resolution_due_at), '30 min + 9h + 9h + 5h30 úteis.');
    }

    public function test_each_business_can_set_its_own_policy_and_hours()
    {
        $this->tenant->slaPolicies()->create(['priority' => 'high', 'first_response_minutes' => 15, 'resolution_minutes' => 60]);
        // Só aos sábados, das 10h às 14h.
        $this->tenant->businessHours()->create(['weekday' => 6, 'starts_at' => '10:00', 'ends_at' => '14:00']);
        $this->travelTo($this->local('2026-10-10 13:50'));

        $ticket = $this->openWithPriority(TicketPriority::High);

        $this->assertSame('2026-10-17 10:05', $this->localTime($ticket->first_response_due_at));
        $this->assertSame('2026-10-17 10:50', $this->localTime($ticket->resolution_due_at));
    }

    public function test_changing_the_priority_recalculates_the_deadlines_from_the_opening()
    {
        $this->travelTo($this->local('2026-10-05 10:00'));
        $ticket = $this->openWithPriority(TicketPriority::Normal);
        $this->travelTo($this->local('2026-10-05 10:20'));

        app(ChangeTicketPriority::class)->handle($ticket, TicketPriority::Urgent, $this->agent);

        $this->assertSame('2026-10-05 10:30', $this->localTime($ticket->fresh()->first_response_due_at));
        $this->assertSame("{$this->agent->name} mudou a prioridade para Urgente", $this->lastHistoryText($ticket));
    }

    public function test_breaches_are_flagged_once_announced_and_emailed_to_the_assignee()
    {
        $this->travelTo($this->local('2026-10-05 10:00'));
        $ticket = $this->openWithPriority(TicketPriority::Urgent); // responder até 10h30
        app(AssignTicket::class)->handle($ticket, $this->agent, $this->owner);
        Notification::fake();
        Event::fake([TicketChanged::class]);

        $this->travelTo($this->local('2026-10-05 10:29'));
        $this->artisan('tickets:check-sla')->expectsOutput('0 prazo(s) estourado(s).');

        $this->travelTo($this->local('2026-10-05 10:31'));
        $this->artisan('tickets:check-sla')->expectsOutput('1 prazo(s) estourado(s).');
        $this->artisan('tickets:check-sla')->expectsOutput('0 prazo(s) estourado(s).');

        $this->assertNotNull($ticket->fresh()->first_response_breached_at);
        $this->assertSame('Prazo de primeira resposta estourado', $this->lastHistoryText($ticket));
        Notification::assertSentToTimes($this->agent, SlaBreached::class, 1);
        Notification::assertNotSentTo($this->owner, SlaBreached::class);
        Event::assertDispatched(TicketChanged::class, fn (TicketChanged $event) => $event->change === TicketChanged::SLA);
    }

    public function test_without_an_assignee_the_managers_are_warned()
    {
        $this->travelTo($this->local('2026-10-05 10:00'));
        $this->openWithPriority(TicketPriority::Urgent);
        Notification::fake();

        $this->travelTo($this->local('2026-10-05 11:00'));
        $this->artisan('tickets:check-sla');

        Notification::assertSentTo($this->owner, SlaBreached::class, fn (SlaBreached $n) => $n->target === 'first_response');
        Notification::assertNotSentTo($this->agent, SlaBreached::class);
    }

    public function test_deadlines_met_in_time_are_not_breaches()
    {
        $this->travelTo($this->local('2026-10-05 10:00'));
        $answered = $this->openWithPriority(TicketPriority::Urgent);
        $resolved = $this->openWithPriority(TicketPriority::Urgent);
        $this->travelTo($this->local('2026-10-05 10:10'));
        app(ReplyToTicket::class)->asAgent($answered, $this->agent, 'Olá!');
        app(ReplyToTicket::class)->asAgent($resolved, $this->agent, 'Resolvido.');
        app(ChangeTicketStatus::class)->handle($resolved, TicketStatus::Resolved, $this->agent);
        Notification::fake();

        // Bem depois dos dois prazos (primeira resposta 10h30, resolução 14h).
        $this->travelTo($this->local('2026-10-06 18:00'));
        $this->artisan('tickets:check-sla')->expectsOutput('0 prazo(s) estourado(s).');

        $this->assertNull($answered->fresh()->first_response_breached_at, 'Respondido a tempo.');
        $this->assertNull($answered->fresh()->resolution_breached_at, 'Aguardando o cliente: o prazo de resolução está pausado.');
        $this->assertNull($resolved->fresh()->resolution_breached_at);
    }

    public function test_waiting_for_the_customer_pauses_the_resolution_deadline()
    {
        $this->travelTo($this->local('2026-10-05 09:00'));
        $ticket = $this->openWithPriority(TicketPriority::Urgent); // resolver até 13h

        // Equipe responde às 10h: faltam 3h úteis, e o relógio para.
        $this->travelTo($this->local('2026-10-05 10:00'));
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Pode confirmar o pedido?');
        $this->assertNotNull($ticket->fresh()->sla_paused_at);

        // O cliente some até o dia seguinte: nada estoura enquanto isso.
        $this->travelTo($this->local('2026-10-05 17:00'));
        $this->artisan('tickets:check-sla')->expectsOutput('0 prazo(s) estourado(s).');

        // Cliente responde terça às 10h: as 3h que faltavam voltam a contar.
        $this->travelTo($this->local('2026-10-06 10:00'));
        app(ReplyToTicket::class)->asCustomer($ticket, $this->customer, 'Confirmo: pedido 48213.');

        $ticket->refresh();
        $this->assertNull($ticket->sla_paused_at);
        $this->assertSame('2026-10-06 13:00', $this->localTime($ticket->resolution_due_at));
        $this->assertSame(540, $ticket->sla_paused_minutes, 'Segunda 10h–18h + terça 9h–10h = 9h úteis pausadas.');

        $this->travelTo($this->local('2026-10-06 13:01'));
        $this->artisan('tickets:check-sla')->expectsOutput('1 prazo(s) estourado(s).');
    }

    public function test_pausing_after_the_deadline_does_not_undo_the_breach()
    {
        $this->travelTo($this->local('2026-10-05 09:00'));
        $ticket = $this->openWithPriority(TicketPriority::Urgent); // resolver até 13h

        $this->travelTo($this->local('2026-10-05 15:00'));
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Desculpe a demora.');
        $this->travelTo($this->local('2026-10-06 10:00'));
        app(ReplyToTicket::class)->asCustomer($ticket, $this->customer, 'Ok.');

        $this->assertSame('2026-10-05 13:00', $this->localTime($ticket->fresh()->resolution_due_at), 'Prazo já vencido não é estendido.');
        $this->assertSame(0, $ticket->fresh()->sla_paused_minutes);
    }

    public function test_paused_time_survives_a_priority_change()
    {
        $this->travelTo($this->local('2026-10-05 09:00'));
        $ticket = $this->openWithPriority(TicketPriority::Urgent);
        $this->travelTo($this->local('2026-10-05 10:00'));
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Pode confirmar?');
        $this->travelTo($this->local('2026-10-06 10:00'));
        app(ReplyToTicket::class)->asCustomer($ticket, $this->customer, 'Confirmo.');

        // Alta: 8h úteis desde a abertura + as 9h pausadas = terça 17h.
        app(ChangeTicketPriority::class)->handle($ticket, TicketPriority::High, $this->agent);

        $this->assertSame('2026-10-06 17:00', $this->localTime($ticket->fresh()->resolution_due_at));
    }

    public function test_the_inbox_shows_a_paused_deadline()
    {
        $this->travelTo($this->local('2026-10-05 09:00'));
        $ticket = $this->openWithPriority(TicketPriority::Urgent);
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Pode confirmar?');
        $this->travelTo($this->local('2026-10-06 10:00'));

        $this->withoutVite()->actingAs($this->agent)
            ->get('/painel/chamados')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tickets.data.0.sla.target', 'resolution')
                ->where('tickets.data.0.sla.paused', true)
                ->where('tickets.data.0.sla.breached', false)
            );
    }

    public function test_the_inbox_shows_the_running_deadline_and_sorts_by_it()
    {
        $this->travelTo($this->local('2026-10-05 10:00'));
        $normal = $this->openWithPriority(TicketPriority::Normal);
        $urgent = $this->openWithPriority(TicketPriority::Urgent);
        $this->travelTo($this->local('2026-10-05 10:40'));

        $this->withoutVite()->actingAs($this->agent)
            ->get('/painel/chamados?sort=due')
            ->assertInertia(fn (Assert $page) => $page
                ->where('sort', 'due')
                ->where('tickets.data.0.number', $urgent->number)
                ->where('tickets.data.0.sla.target', 'first_response')
                ->where('tickets.data.0.sla.breached', true)
                ->where('tickets.data.1.number', $normal->number)
                ->where('tickets.data.1.sla.breached', false)
            );
    }

    private function openWithPriority(TicketPriority $priority): Ticket
    {
        return app(OpenTicket::class)->handle($this->customer, 'Pedido', 'Detalhes', TicketChannel::Portal, $priority);
    }

    private function local(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, self::TZ);
    }

    private function localTime(?CarbonImmutable $instant): ?string
    {
        return $instant?->setTimezone(self::TZ)->format('Y-m-d H:i');
    }

    private function lastHistoryText(Ticket $ticket): string
    {
        $event = TicketEvent::withoutGlobalScopes()->where('ticket_id', $ticket->id)->latest('id')->firstOrFail();
        $names = $this->tenant->users()->pluck('name', 'users.id');

        return app(TicketEventDescriber::class)->describe($event, $names);
    }
}
