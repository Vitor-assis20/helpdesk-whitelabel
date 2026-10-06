<?php

namespace Tests\Feature\Widget;

use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Helpdesk\ChangeTicketStatus;
use App\Helpdesk\ReplyToTicket;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Notifications\TicketReceived;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Helpdesk\HelpdeskTestCase;

/**
 * API chamada pelo widget de dentro do site da empresa (outra origem):
 * JSON, sem sessão nem cookies, acesso ao chamado pelo token.
 */
class WidgetApiTest extends HelpdeskTestCase
{
    private const SITE = 'https://www.lojaaurora.com.br';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant->update(['slug' => 'aurora', 'branding' => ['primary_color' => '#7c3aed', 'logo_path' => null]]);
    }

    public function test_the_script_carries_the_business_config_and_sets_no_cookies()
    {
        $response = $this->get($this->url('widget.js'));

        $response->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $this->assertSame([], $response->headers->getCookies(), 'O script roda no site da empresa: nada de cookies.');
        $this->assertStringStartsWith('window.__HELPDESK_WIDGET__=', $response->getContent());
        $this->assertStringContainsString('"name":"Loja Aurora"', $response->getContent());
        $this->assertStringContainsString('"color":"#7c3aed","textColor":"#ffffff"', $response->getContent());
        $this->assertStringContainsString('"apiUrl":"http://aurora.localhost/api/widget"', $response->getContent());
    }

    public function test_another_site_can_call_the_api_without_credentials()
    {
        $this->call('OPTIONS', $this->url('widget/tickets'), server: [
            'HTTP_ORIGIN' => self::SITE,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-ticket-token',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_opens_a_ticket_from_the_widget()
    {
        Notification::fake();

        $response = $this->withHeader('Origin', self::SITE)->postJson($this->url('widget/tickets'), [
            'name' => 'Juliana Prado', 'email' => 'juliana@example.com', 'subject' => 'Cor errada', 'body' => 'Veio verde.',
        ]);

        $ticket = Ticket::withoutGlobalScopes()->sole();
        $response->assertCreated()
            ->assertJsonPath('ticket.token', $ticket->access_token)
            ->assertJsonPath('ticket.number', $ticket->number)
            ->assertJsonPath('messages.0.from_customer', true);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame(TicketChannel::Widget, $ticket->channel);
        Notification::assertSentOnDemand(TicketReceived::class);
    }

    public function test_validation_errors_come_back_as_json_in_portuguese()
    {
        $this->postJson($this->url('widget/tickets'), ['name' => 'Ana', 'email' => 'nao-e-email'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Informe um e-mail válido.')
            ->assertJsonPath('errors.subject.0', 'Preencha este campo.');
    }

    public function test_the_conversation_never_includes_internal_notes()
    {
        $ticket = $this->openTicket();
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'NOTA SECRETA', internal: true);
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Olá!');

        $response = $this->getJson($this->url("widget/tickets/{$ticket->access_token}"))->assertOk();

        $this->assertSame(['Comprei há 10 dias e nada.', 'Olá!'], array_column($response->json('messages'), 'body'));
        $this->assertStringNotContainsString('NOTA SECRETA', $response->getContent());
    }

    public function test_the_customer_replies_through_the_widget()
    {
        $ticket = $this->openTicket();
        app(ReplyToTicket::class)->asAgent($ticket, $this->agent, 'Pode mandar o número do pedido?');

        $this->postJson($this->url("widget/tickets/{$ticket->access_token}/messages"), ['body' => 'É o 48213.'])
            ->assertCreated()
            ->assertJsonPath('ticket.status', 'open')
            ->assertJsonCount(3, 'messages');
    }

    public function test_closed_tickets_and_unknown_tokens_are_refused()
    {
        $ticket = $this->openTicket();
        app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Closed, $this->agent);

        $this->postJson($this->url("widget/tickets/{$ticket->access_token}/messages"), ['body' => 'Oi?'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.body.0', 'Este chamado está encerrado. Abra um novo chamado para continuar o atendimento.');

        $this->getJson($this->url('widget/tickets/'.str_repeat('x', 40)))->assertNotFound();
        Tenant::factory()->create(['slug' => 'outra']);
        $this->getJson("http://outra.localhost/api/widget/tickets/{$ticket->access_token}")->assertNotFound();
    }

    public function test_the_realtime_channel_is_authorized_by_the_token_without_a_session()
    {
        $ticket = $this->openTicket();
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        $auth = fn (string $token) => $this->withHeaders(['Origin' => self::SITE, 'X-Ticket-Token' => $token])
            ->post($this->url('widget/broadcasting/auth'), ['socket_id' => '1234.5678', 'channel_name' => "private-portal.tickets.{$ticket->id}"]);

        $auth($ticket->access_token)->assertOk()->assertJsonPath('auth', 'test-key:'.hash_hmac('sha256', "1234.5678:private-portal.tickets.{$ticket->id}", 'test-secret'));
        $auth('errado')->assertForbidden();
    }

    public function test_the_demo_store_embeds_the_widget_from_the_business_subdomain()
    {
        $this->get('http://localhost/demo/aurora')
            ->assertOk()
            ->assertSee('<script src="http://aurora.localhost/api/widget.js" async></script>', escape: false);
    }

    private function url(string $path): string
    {
        return 'http://aurora.localhost/api/'.ltrim($path, '/');
    }
}
