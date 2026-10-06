<?php

namespace Tests\Feature\Panel;

use App\Enums\MessageKind;
use App\Enums\TicketStatus;
use App\Helpdesk\AssignTicket;
use App\Helpdesk\ChangeTicketStatus;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TicketMessage;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Helpdesk\HelpdeskTestCase;

class TicketPanelTest extends HelpdeskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_and_outsiders_are_kept_out()
    {
        $this->get('/painel/chamados')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/painel/chamados')
            ->assertInertia(fn (Assert $page) => $page->component('panel/no-business'));
    }

    public function test_dashboard_leads_to_the_inbox()
    {
        $this->actingAs($this->agent)->get('/dashboard')->assertRedirect('/painel/chamados');
    }

    public function test_the_inbox_lists_active_tickets_by_latest_activity_with_counts_per_view()
    {
        $older = $this->openTicket(subject: 'Mais antigo');
        $this->travel(1)->hours();
        $mine = $this->openTicket(subject: 'Meu chamado');
        app(AssignTicket::class)->handle($mine, $this->agent, $this->owner);
        $done = $this->openTicket(subject: 'Já resolvido');
        app(ChangeTicketStatus::class)->handle($done, TicketStatus::Resolved, $this->agent);
        $this->openTicket(Customer::factory()->for(Tenant::factory())->create(), 'De outra empresa');

        $this->actingAs($this->agent)
            ->get('/painel/chamados')
            ->assertInertia(fn (Assert $page) => $page
                ->component('panel/tickets/index')
                ->where('view', 'open')
                ->where('counts', ['open' => 2, 'mine' => 1, 'unassigned' => 1, 'done' => 1])
                ->has('tickets.data', 2)
                ->where('tickets.data.0.subject', 'Meu chamado')
                ->where('tickets.data.0.assignee', $this->agent->name)
                ->where('tickets.data.1.subject', 'Mais antigo')
            );

        $this->get('/painel/chamados?view=done')
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.number', $done->number));
    }

    public function test_tickets_are_addressed_by_number_within_the_current_business()
    {
        $ours = $this->openTicket(subject: 'Nosso #1');
        $theirs = $this->openTicket(Customer::factory()->for(Tenant::factory())->create(), 'Deles #1');
        $this->assertSame($ours->number, $theirs->number);

        $this->actingAs($this->agent)
            ->get("/painel/chamados/{$ours->number}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('panel/tickets/show')
                ->where('ticket.subject', 'Nosso #1')
                ->has('messages', 1)
                ->where('messages.0.author_type', 'customer')
                ->where('events.0.text', 'Chamado aberto pelo cliente (portal)')
            );

        $this->get('/painel/chamados/999')->assertNotFound();
    }

    public function test_the_team_replies_and_leaves_internal_notes()
    {
        $ticket = $this->openTicket();
        $this->actingAs($this->agent);

        $this->post("/painel/chamados/{$ticket->number}/mensagens", ['body' => 'Verificando com a transportadora.'])->assertSessionHasNoErrors();
        $this->post("/painel/chamados/{$ticket->number}/mensagens", ['body' => 'Cliente VIP.', 'internal' => true])->assertSessionHasNoErrors();
        $this->post("/painel/chamados/{$ticket->number}/mensagens", ['body' => ''])->assertSessionHasErrors(['body' => 'Escreva a mensagem.']);

        $messages = TicketMessage::withoutGlobalScopes()->where('ticket_id', $ticket->id)->orderBy('id')->get();
        $this->assertSame([MessageKind::Reply, MessageKind::Reply, MessageKind::Note], $messages->pluck('kind')->all());
        $this->assertSame(TicketStatus::Pending, $ticket->fresh()->status);

        $this->get("/painel/chamados/{$ticket->number}")->assertInertia(fn (Assert $page) => $page
            ->where('messages.2.kind', 'note')
            ->where('messages.2.author', $this->agent->name)
        );
    }

    public function test_status_changes_follow_the_lifecycle_and_explain_refusals()
    {
        $ticket = $this->openTicket();
        $this->actingAs($this->agent);

        $this->patch("/painel/chamados/{$ticket->number}/status", ['status' => 'closed'])->assertSessionHasNoErrors();

        $this->patch("/painel/chamados/{$ticket->number}/status", ['status' => 'open'])
            ->assertSessionHasErrors(['status' => 'Um chamado "Encerrado" não pode passar para "Em atendimento".']);
        $this->post("/painel/chamados/{$ticket->number}/mensagens", ['body' => 'Oi'])
            ->assertSessionHasErrors('body');
    }

    public function test_assigning_and_taking_a_ticket()
    {
        $ticket = $this->openTicket();
        $this->actingAs($this->owner);

        $this->patch("/painel/chamados/{$ticket->number}/responsavel", ['assignee_id' => $this->agent->id])->assertSessionHasNoErrors();
        $this->assertSame($this->agent->id, $ticket->fresh()->assignee_id);

        $this->patch("/painel/chamados/{$ticket->number}/responsavel", ['assignee_id' => User::factory()->create()->id])
            ->assertSessionHasErrors(['assignee_id' => 'Este usuário não faz parte da equipe desta empresa.']);
        $this->patch("/painel/chamados/{$ticket->number}/responsavel", ['assignee_id' => 999999])->assertSessionHasErrors('assignee_id');

        $this->patch("/painel/chamados/{$ticket->number}/responsavel", ['assignee_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($ticket->fresh()->assignee_id);
    }

    public function test_the_history_reads_naturally()
    {
        $ticket = $this->openTicket();
        $this->actingAs($this->agent)->patch("/painel/chamados/{$ticket->number}/responsavel", ['assignee_id' => $this->agent->id]);
        $this->post("/painel/chamados/{$ticket->number}/mensagens", ['body' => 'Olá!']);

        $this->get("/painel/chamados/{$ticket->number}")->assertInertia(fn (Assert $page) => $page->where('events', fn ($events) => collect($events)->pluck('text')->all() === [
            'Chamado aberto pelo cliente (portal)',
            "{$this->agent->name} assumiu o chamado",
            'Status: Em atendimento',
            'Status: Aguardando cliente',
        ]));
    }
}
