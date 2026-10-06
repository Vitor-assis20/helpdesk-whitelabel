<?php

namespace Tests\Feature\Panel;

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Helpdesk\OpenTicket;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Helpdesk\HelpdeskTestCase;

class SettingsTest extends HelpdeskTestCase
{
    private const TZ = 'America/Sao_Paulo';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->tenant->update(['slug' => 'aurora']);
    }

    public function test_shows_the_defaults_when_nothing_was_configured()
    {
        $this->actingAs($this->owner)
            ->get('/painel/configuracoes')
            ->assertInertia(fn (Assert $page) => $page
                ->component('panel/settings')
                // A porta acompanha o APP_URL do ambiente.
                ->where('business.portal_url', fn (string $url) => preg_match('#^http://aurora\.localhost(:\d+)?$#', $url) === 1)
                ->where('business.widget_snippet', fn (string $snippet) => preg_match('#^<script src="http://aurora\.localhost(:\d+)?/api/widget\.js" async></script>$#', $snippet) === 1)
                ->has('hours', 5)
                ->where('hours.0', ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00'])
                ->where('policies.urgent', ['label' => 'Urgente', 'first_response_minutes' => 30, 'resolution_minutes' => 240])
            );
    }

    public function test_agents_cannot_change_settings()
    {
        $this->actingAs($this->agent)->get('/painel/configuracoes')->assertForbidden();
        $this->actingAs($this->agent)->put('/painel/configuracoes/sla', $this->policies())->assertForbidden();
    }

    public function test_updates_the_brand_shown_to_customers()
    {
        $this->actingAs($this->owner)
            ->put('/painel/configuracoes/marca', ['name' => 'Aurora Moda', 'primary_color' => '#1D4ED8'])
            ->assertSessionHasNoErrors();

        $this->get('http://aurora.localhost/')->assertInertia(fn (Assert $page) => $page
            ->where('tenant.name', 'Aurora Moda')
            ->where('tenant.branding.primary_color', '#1d4ed8')
        );

        // URL absoluta: depois de uma requisição ao portal, caminhos relativos iriam para o subdomínio.
        $this->put('http://localhost/painel/configuracoes/marca', ['name' => 'X', 'primary_color' => 'red; x'])->assertSessionHasErrors('primary_color');
    }

    public function test_new_business_hours_drive_the_deadlines_of_new_tickets()
    {
        $this->actingAs($this->owner)->put('/painel/configuracoes/expediente', ['hours' => [
            ['weekday' => 6, 'starts_at' => '08:00', 'ends_at' => '12:00'], // só sábado de manhã
        ]])->assertSessionHasNoErrors();

        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00', self::TZ)); // sexta
        $ticket = $this->open(TicketPriority::Urgent);

        // 30 min úteis a partir de sábado, 8h.
        $this->assertSame('2026-10-10 08:30', $ticket->first_response_due_at->setTimezone(self::TZ)->format('Y-m-d H:i'));
    }

    public function test_rejects_overlapping_or_inverted_business_hours()
    {
        $this->actingAs($this->owner);

        $this->put('/painel/configuracoes/expediente', ['hours' => [['weekday' => 1, 'starts_at' => '18:00', 'ends_at' => '09:00']]])
            ->assertSessionHasErrors(['hours.0.ends_at' => 'O fim deve ser depois do início.']);
        $this->put('/painel/configuracoes/expediente', ['hours' => [
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
            ['weekday' => 1, 'starts_at' => '11:00', 'ends_at' => '14:00'],
        ]])->assertSessionHasErrors(['hours.1.starts_at' => 'Este intervalo se sobrepõe a outro do mesmo dia.']);
    }

    public function test_new_sla_policies_apply_to_new_tickets_only()
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00', self::TZ)); // segunda
        $before = $this->open(TicketPriority::Normal);

        $this->actingAs($this->owner)
            ->put('/painel/configuracoes/sla', $this->policies(['normal' => ['first_response_minutes' => 60, 'resolution_minutes' => 120]]))
            ->assertSessionHasNoErrors();
        $after = $this->open(TicketPriority::Normal);

        $this->assertSame('11:00', $after->first_response_due_at->setTimezone(self::TZ)->format('H:i'));
        $this->assertSame('14:00', $before->fresh()->first_response_due_at->setTimezone(self::TZ)->format('H:i'), 'Chamado já aberto mantém o prazo.');
        $this->assertSame(4, $this->tenant->slaPolicies()->count());
    }

    public function test_resolution_cannot_come_before_the_first_response()
    {
        $this->actingAs($this->owner)
            ->put('/painel/configuracoes/sla', $this->policies(['high' => ['first_response_minutes' => 120, 'resolution_minutes' => 60]]))
            ->assertSessionHasErrors(['policies.high.resolution_minutes' => 'A resolução não pode vir antes da primeira resposta.']);
    }

    /**
     * @param  array<string, array{first_response_minutes: int, resolution_minutes: int}>  $overrides
     * @return array{policies: array<string, array{first_response_minutes: int, resolution_minutes: int}>}
     */
    private function policies(array $overrides = []): array
    {
        return ['policies' => [...SlaPolicy::DEFAULTS, ...$overrides]];
    }

    private function open(TicketPriority $priority): Ticket
    {
        return app(OpenTicket::class)->handle($this->customer, 'Assunto', 'Mensagem', TicketChannel::Portal, $priority);
    }
}
