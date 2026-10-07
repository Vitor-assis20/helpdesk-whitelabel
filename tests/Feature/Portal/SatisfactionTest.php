<?php

namespace Tests\Feature\Portal;

use App\Enums\SatisfactionScore;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Helpdesk\ChangeTicketStatus;
use App\Helpdesk\RateTicket;
use App\Models\Ticket;
use App\Notifications\TicketResolved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Helpdesk\HelpdeskTestCase;

/**
 * Avaliação do atendimento (CSAT) pelo cliente: portal, widget e e-mail.
 */
class SatisfactionTest extends HelpdeskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->tenant->update(['slug' => 'aurora']);
    }

    public function test_the_customer_rates_a_resolved_ticket_on_the_portal()
    {
        $ticket = $this->resolvedTicket();

        $this->get($this->portal($ticket))->assertInertia(fn (Assert $page) => $page
            ->where('ticket.can_rate', true)
            ->where('ticket.rating', null)
            ->where('suggested_score', null)
        );

        $this->post($this->portal($ticket, '/avaliacao'), ['score' => 4, 'comment' => '  Resolveram rápido.  '])
            ->assertRedirect($this->portal($ticket));

        $ticket->refresh();
        $this->assertSame(SatisfactionScore::Good, $ticket->satisfaction_score);
        $this->assertSame('Resolveram rápido.', $ticket->satisfaction_comment);
        $this->assertNotNull($ticket->satisfaction_rated_at);

        $this->get($this->portal($ticket))->assertInertia(fn (Assert $page) => $page
            ->where('ticket.rating', ['score' => 4, 'label' => 'Bom', 'comment' => 'Resolveram rápido.'])
        );
    }

    public function test_rating_again_replaces_the_score_and_both_stay_in_the_history()
    {
        $ticket = $this->resolvedTicket();
        $rate = app(RateTicket::class);

        $rate->handle($ticket, SatisfactionScore::Bad, 'Demorou.');
        $rate->handle($ticket, SatisfactionScore::Great);

        $this->assertSame(SatisfactionScore::Great, $ticket->refresh()->satisfaction_score);
        $this->assertNull($ticket->satisfaction_comment);

        $this->actingAs($this->agent)
            ->get("/painel/chamados/{$ticket->number}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('ticket.rating.score', 5)
                ->where('ticket.rating.label', 'Ótimo')
                ->where('events', fn ($events) => collect($events)->pluck('text')->intersect([
                    'O cliente avaliou o atendimento: Ruim (2/5)',
                    'O cliente mudou a avaliação para Ótimo (5/5)',
                ])->count() === 2)
            );
    }

    public function test_only_resolved_or_closed_tickets_can_be_rated()
    {
        $ticket = $this->openTicket();

        $this->get($this->portal($ticket))->assertInertia(fn (Assert $page) => $page->where('ticket.can_rate', false));
        $this->post($this->portal($ticket, '/avaliacao'), ['score' => 5])
            ->assertSessionHasErrors(['score' => 'Você poderá avaliar o atendimento quando o chamado for resolvido.']);

        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Closed, $this->agent);
        $this->post($this->portal($ticket, '/avaliacao'), ['score' => 5])->assertSessionHasNoErrors();

        $this->assertSame(SatisfactionScore::Great, $ticket->refresh()->satisfaction_score);
    }

    public function test_rejects_invalid_scores_and_unknown_links()
    {
        $ticket = $this->resolvedTicket();

        foreach ([0, 6, 'cinco', null] as $score) {
            $this->post($this->portal($ticket, '/avaliacao'), ['score' => $score])->assertSessionHasErrors(['score' => 'Escolha uma nota de 1 a 5.']);
        }
        $this->post($this->portal($ticket, '/avaliacao'), ['score' => 3, 'comment' => str_repeat('a', 1001)])->assertSessionHasErrors('comment');

        $this->post('http://aurora.localhost/chamados/'.str_repeat('x', 40).'/avaliacao', ['score' => 5])->assertNotFound();
        $this->assertNull($ticket->refresh()->satisfaction_score);
    }

    public function test_the_resolved_email_links_each_score_to_the_portal_without_saving_it()
    {
        Notification::fake();
        $ticket = $this->openTicket();

        $this->actingAs($this->agent)->patch("/painel/chamados/{$ticket->number}/status", ['status' => 'resolved']);

        Notification::assertSentOnDemand(TicketResolved::class, function (TicketResolved $notification) use ($ticket) {
            $html = (string) $notification->toMail((object) [])->render();

            return str_contains($html, 'Como foi o atendimento?')
                // A porta acompanha o APP_URL do ambiente: confere só o caminho.
                && str_contains($html, "/chamados/{$ticket->access_token}?nota=1#avaliacao\"")
                && str_contains($html, '5 · Ótimo');
        });

        // O link só pré-seleciona a nota: leitores de e-mail abrem links sozinhos.
        $this->get($this->portal($ticket).'?nota=5')->assertInertia(fn (Assert $page) => $page->where('suggested_score', 5));
        $this->get($this->portal($ticket).'?nota=9')->assertInertia(fn (Assert $page) => $page->where('suggested_score', null));
        $this->assertNull($ticket->refresh()->satisfaction_score);
    }

    public function test_the_widget_rates_through_the_json_api()
    {
        $ticket = $this->resolvedTicket();

        $this->postJson("http://aurora.localhost/api/widget/tickets/{$ticket->access_token}/rating", ['score' => 2])
            ->assertOk()
            ->assertJsonPath('ticket.rating', ['score' => 2, 'label' => 'Ruim', 'comment' => null]);

        $this->postJson("http://aurora.localhost/api/widget/tickets/{$this->openTicket()->access_token}/rating", ['score' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('score');
    }

    public function test_the_team_is_told_in_real_time()
    {
        $ticket = $this->resolvedTicket();
        Event::fake([TicketChanged::class]);

        app(RateTicket::class)->handle($ticket, SatisfactionScore::Okay);

        Event::assertDispatched(TicketChanged::class, fn (TicketChanged $event) => $event->change === TicketChanged::RATING
            && $event->ticketId === $ticket->id
            && $event->visibleToCustomer);
    }

    public function test_the_database_keeps_ratings_consistent()
    {
        $ticket = $this->resolvedTicket();
        $update = fn (array $values) => fn () => Ticket::withoutGlobalScopes()->whereKey($ticket->id)->update($values);

        $this->assertRejectedByDatabase('23514', $update(['satisfaction_score' => 6, 'satisfaction_rated_at' => now()]));
        $this->assertRejectedByDatabase('23514', $update(['satisfaction_score' => 4]));
        $this->assertRejectedByDatabase('23514', $update(['satisfaction_comment' => 'Sem nota']));
    }

    private function resolvedTicket(): Ticket
    {
        $ticket = $this->openTicket();
        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Resolved, $this->agent);

        return $ticket;
    }

    private function portal(Ticket $ticket, string $suffix = ''): string
    {
        return "http://aurora.localhost/chamados/{$ticket->access_token}{$suffix}";
    }
}
