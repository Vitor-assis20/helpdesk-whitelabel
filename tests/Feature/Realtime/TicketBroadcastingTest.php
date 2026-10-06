<?php

namespace Tests\Feature\Realtime;

use App\Enums\TicketStatus;
use App\Events\TicketChanged;
use App\Helpdesk\AssignTicket;
use App\Helpdesk\ChangeTicketStatus;
use App\Helpdesk\ReplyToTicket;
use App\Helpdesk\TicketActionFailed;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Helpdesk\HelpdeskTestCase;

class TicketBroadcastingTest extends HelpdeskTestCase
{
    public function test_every_change_is_announced_to_the_business_and_the_ticket()
    {
        Event::fake([TicketChanged::class]);

        $ticket = $this->openTicket();
        app(AssignTicket::class)->handle($ticket, $this->agent, $this->owner);
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Olá!');
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Nota', internal: true);
        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Resolved, $this->agent);

        $changes = [];
        Event::assertDispatched(TicketChanged::class, function (TicketChanged $event) use ($ticket, &$changes) {
            $changes[] = $event->change;

            return $event->ticketId === $ticket->id && $event->tenantId === $this->tenant->id;
        });
        $this->assertSame(['created', 'assignment', 'message', 'message', 'status'], $changes);
    }

    public function test_the_payload_carries_no_content()
    {
        $event = new TicketChanged(tenantId: 7, ticketId: 42, number: 3, change: TicketChanged::MESSAGE);

        $this->assertSame(['ticket_id' => 42, 'number' => 3, 'change' => 'message'], $event->broadcastWith());
        $this->assertSame('ticket.changed', $event->broadcastAs());
        $this->assertSame(
            ['private-tenants.7.tickets', 'private-tickets.42'],
            array_map(fn ($channel) => $channel->name, $event->broadcastOn()),
        );
    }

    public function test_failed_actions_announce_nothing()
    {
        $ticket = $this->openTicket();
        Event::fake([TicketChanged::class]);

        try {
            app(ReplyToTicket::class)->asAgent($ticket, User::factory()->create(), 'Intruso');
        } catch (TicketActionFailed) {
        }

        Event::assertNotDispatched(TicketChanged::class);
    }

    public function test_only_the_team_can_join_the_channels()
    {
        $ticket = $this->openTicket();
        $otherTicket = $this->openTicket(Customer::factory()->for(Tenant::factory())->create());

        // Driver do Reverb só para assinar as respostas de autorização (sem conexão com o servidor).
        // Configurado depois de abrir os chamados, para os eventos não tentarem publicar.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        // Os canais são registrados no broadcaster padrão do boot (null, nos testes):
        // registra as mesmas regras no Reverb que acabou de virar o padrão.
        require base_path('routes/channels.php');

        $auth = fn (User $user, string $channel) => $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);

        $auth($this->agent, "private-tenants.{$this->tenant->id}.tickets")->assertOk();
        $auth($this->agent, "private-tickets.{$ticket->id}")->assertOk();
        $auth($this->agent, "private-tickets.{$otherTicket->id}")->assertForbidden();
        $auth($this->agent, "private-tenants.{$otherTicket->tenant_id}.tickets")->assertForbidden();
        $auth(User::factory()->create(), "private-tickets.{$ticket->id}")->assertForbidden();

        // Presença: identifica quem está vendo.
        $presence = $auth($this->agent, "presence-tickets.{$ticket->id}.viewers")->assertOk();
        $this->assertSame(
            // O protocolo do Pusher (seguido pelo Reverb) envia o user_id como texto.
            ['user_id' => (string) $this->agent->id, 'user_info' => ['id' => $this->agent->id, 'name' => $this->agent->name]],
            json_decode($presence->json('channel_data'), true),
        );
    }
}
