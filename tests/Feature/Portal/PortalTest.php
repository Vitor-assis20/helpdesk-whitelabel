<?php

namespace Tests\Feature\Portal;

use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Helpdesk\ChangeTicketStatus;
use App\Helpdesk\ReplyToTicket;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Notifications\AgentReplied;
use App\Notifications\TicketReceived;
use App\Notifications\TicketResolved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Helpdesk\HelpdeskTestCase;

class PortalTest extends HelpdeskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->tenant->update(['slug' => 'aurora', 'branding' => ['primary_color' => '#7c3aed', 'logo_path' => null]]);
    }

    public function test_the_portal_lives_on_the_business_subdomain_with_its_brand()
    {
        $this->get($this->url('/'))
            ->assertOk()
            ->assertSee('<title inertia>Loja Aurora</title>', escape: false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/home')
                ->where('tenant.name', 'Loja Aurora')
                ->where('tenant.branding.primary_color', '#7c3aed')
            );

        $this->get('http://inexistente.localhost/')->assertNotFound();
        $this->get($this->url('login'))->assertNotFound();
        $this->get($this->url('painel/chamados'))->assertNotFound();
    }

    public function test_a_customer_opens_a_ticket_and_receives_the_link_by_email()
    {
        Notification::fake();

        $response = $this->post($this->url('chamados'), [
            'name' => 'Marina Costa', 'email' => 'MARINA@example.com', 'subject' => 'Pedido não chegou', 'body' => 'Faz 10 dias.',
        ]);

        $ticket = Ticket::withoutGlobalScopes()->latest('id')->first();
        $response->assertRedirect($this->url("chamados/{$ticket->access_token}"));
        $this->assertSame(TicketChannel::Portal, $ticket->channel);
        $this->assertSame('marina@example.com', $ticket->customer()->withoutGlobalScopes()->value('email'));

        Notification::assertSentOnDemand(TicketReceived::class, fn (TicketReceived $notification, array $channels, $notifiable) => $notifiable->routes['mail'] === ['marina@example.com' => 'Marina Costa']
            && str_ends_with($notification->summary->ticketUrl, "/chamados/{$ticket->access_token}")
            && ! str_contains(serialize($notification), 'App\\Models\\'));
    }

    public function test_the_customer_never_sees_internal_notes()
    {
        $ticket = $this->openTicket();
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'NOTA SECRETA: cliente difícil', internal: true);
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Olá! Já estamos verificando.');

        $this->get($this->url("chamados/{$ticket->access_token}"))
            ->assertOk()
            ->assertDontSee('NOTA SECRETA')
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/ticket')
                ->has('messages', 2)
                ->where('messages.0.from_customer', true)
                ->where('messages.1.body', 'Olá! Já estamos verificando.')
                ->where('messages.1.author', $this->agent->name)
            );
    }

    public function test_the_link_only_works_with_the_right_token_on_the_right_subdomain()
    {
        $ticket = $this->openTicket();
        $elsewhere = Tenant::factory()->create(['slug' => 'outra']);

        $this->get($this->url('chamados/'.str_repeat('x', 40)))->assertNotFound();
        $this->get("http://outra.localhost/chamados/{$ticket->access_token}")->assertNotFound();
        $this->assertNotNull($elsewhere);
    }

    public function test_the_customer_replies_and_reopens_the_ticket()
    {
        $ticket = $this->openTicket();
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Pode mandar o número do pedido?');

        $this->post($this->url("chamados/{$ticket->access_token}/mensagens"), ['body' => 'É o 48213.'])->assertSessionHasNoErrors();

        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
    }

    public function test_a_closed_ticket_explains_why_it_takes_no_replies()
    {
        $ticket = $this->openTicket();
        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Closed, $this->agent);

        $this->post($this->url("chamados/{$ticket->access_token}/mensagens"), ['body' => 'Oi?'])
            ->assertSessionHasErrors(['body' => 'Este chamado está encerrado. Abra um novo chamado para continuar o atendimento.']);
    }

    public function test_the_customer_is_emailed_about_replies_and_resolution_but_not_notes()
    {
        $ticket = $this->openTicket();
        Notification::fake();
        $this->actingAs($this->agent);

        $this->post("http://localhost/painel/chamados/{$ticket->number}/mensagens", ['body' => 'Nota interna', 'internal' => true]);
        Notification::assertNothingSent();

        $this->post("http://localhost/painel/chamados/{$ticket->number}/mensagens", ['body' => 'Seu pedido sai hoje.']);
        Notification::assertSentOnDemand(AgentReplied::class, fn (AgentReplied $n) => $n->reply === 'Seu pedido sai hoje.' && $n->agentName === $this->agent->name);

        $this->patch("http://localhost/painel/chamados/{$ticket->number}/status", ['status' => 'resolved']);
        Notification::assertSentOnDemand(TicketResolved::class);
    }

    public function test_internal_notes_are_not_announced_on_the_customer_channel()
    {
        $ticket = $this->openTicket();
        Event::fake([TicketChanged::class]);

        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Nota', internal: true);
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Resposta');

        $portalChannels = [];
        Event::assertDispatched(TicketChanged::class, function (TicketChanged $event) use (&$portalChannels) {
            $portalChannels[] = collect($event->broadcastOn())->contains(fn ($channel) => str_starts_with($channel->name, 'private-portal.'));

            return true;
        });
        $this->assertSame([false, true], $portalChannels, 'A nota não vai para o canal do cliente; a resposta vai.');
    }

    public function test_the_customer_channel_is_authorized_by_the_ticket_token()
    {
        $ticket = $this->openTicket();
        $other = $this->openTicket(Customer::factory()->for($this->tenant)->create());
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        $auth = fn (Ticket $channelTicket, string $token) => $this->withHeader('X-Ticket-Token', $token)->post($this->url('portal/broadcasting/auth'), [
            'socket_id' => '1234.5678',
            'channel_name' => "private-portal.tickets.{$channelTicket->id}",
        ]);

        $response = $auth($ticket, $ticket->access_token)->assertOk();
        // Assinatura HMAC no formato do protocolo do Pusher, verificável pelo Reverb.
        $this->assertSame(
            'test-key:'.hash_hmac('sha256', "1234.5678:private-portal.tickets.{$ticket->id}", 'test-secret'),
            $response->json('auth'),
        );

        $auth($ticket, str_repeat('x', 40))->assertForbidden();
        $auth($other, $ticket->access_token)->assertForbidden();
    }

    private function url(string $path): string
    {
        return 'http://aurora.localhost/'.ltrim($path, '/');
    }
}
