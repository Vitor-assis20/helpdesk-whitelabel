<?php

namespace Tests\Feature\Panel;

use App\Helpdesk\ReplyToTicket;
use App\Models\Customer;
use App\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Helpdesk\HelpdeskTestCase;

class TicketSearchTest extends HelpdeskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_finds_by_subject_ignoring_accents_and_word_endings()
    {
        $this->openTicket(subject: 'Cobrança em duplicidade');
        $this->openTicket(subject: 'Troca de tamanho');

        $this->assertSearchFinds('cobranca', ['Cobrança em duplicidade']);
        $this->assertSearchFinds('COBRANÇAS', ['Cobrança em duplicidade']);
    }

    public function test_finds_while_typing_by_prefix()
    {
        $this->openTicket(subject: 'Reembolso não caiu');

        $this->assertSearchFinds('reemb', ['Reembolso não caiu']);
    }

    public function test_finds_inside_messages_including_internal_notes()
    {
        $public = $this->openTicket(subject: 'Pedido atrasado');
        $noted = $this->openTicket(subject: 'Dúvida geral');
        app(ReplyToTicket::class)->asAgent($public, $this->agent, 'A transportadora confirmou o extravio.');
        app(ReplyToTicket::class)->asAgent($noted, $this->agent, 'Possível fraude, verificar extravio.', internal: true);

        $this->actingAs($this->agent)
            ->get('/painel/chamados?q=extravio')
            ->assertInertia(fn (Assert $page) => $page
                ->has('tickets.data', 2)
                ->where('tickets.data', fn ($tickets) => collect($tickets)->contains(fn ($ticket) => $ticket['snippet']['note'] === true
                    && collect($ticket['snippet']['segments'])->contains(['text' => 'extravio', 'match' => true])))
            );
    }

    public function test_finds_by_number_and_by_customer()
    {
        $this->openTicket(subject: 'Primeiro');
        $second = $this->openTicket(Customer::factory()->for($this->tenant)->create(['name' => 'Joaquim Patrício', 'email' => 'jp@empresa.com']), 'Segundo');

        $this->assertSearchFinds("#{$second->number}", ['Segundo']);
        $this->assertSearchFinds('patricio', ['Segundo']);
        $this->assertSearchFinds('jp@empresa', ['Segundo']);
    }

    public function test_subject_matches_rank_above_message_matches()
    {
        $inBody = $this->openTicket(subject: 'Outro assunto');
        app(ReplyToTicket::class)->asAgent($inBody, $this->agent, 'Sobre o cupom de desconto…');
        $this->travel(1)->minutes();
        $inSubject = $this->openTicket(subject: 'Cupom não aplicou');
        // A atividade mais recente é do chamado que só cita o termo no corpo.
        $this->travel(1)->minutes();
        app(ReplyToTicket::class)->asAgent($inBody, $this->agent, 'Mais uma mensagem.');

        $this->assertSearchFinds('cupom', ['Cupom não aplicou', 'Outro assunto']);
        $this->assertNotNull($inSubject);
    }

    public function test_searches_only_the_current_business()
    {
        $this->openTicket(subject: 'Cobrança daqui');
        $this->openTicket(Customer::factory()->for(Tenant::factory())->create(), 'Cobrança de outra empresa');

        $this->assertSearchFinds('cobranca', ['Cobrança daqui']);
    }

    public function test_hostile_input_never_breaks_the_query()
    {
        $this->openTicket(subject: 'Cobrança em duplicidade');

        foreach (["'", '":*', '& | ! ( )', '<script>', 'cobr\\ança', '%_', str_repeat('a ', 50), '🙂'] as $input) {
            $this->actingAs($this->agent)->get('/painel/chamados?q='.urlencode($input))->assertOk();
        }

        $this->assertSearchFinds('"cobrança" & duplicidade!', ['Cobrança em duplicidade']);
    }

    /**
     * @param  list<string>  $subjects  Na ordem esperada.
     */
    private function assertSearchFinds(string $query, array $subjects): void
    {
        $this->actingAs($this->agent)
            ->get('/painel/chamados?q='.urlencode($query))
            ->assertInertia(fn (Assert $page) => $page
                ->where('q', $query)
                ->where('tickets.data', fn ($tickets) => collect($tickets)->pluck('subject')->all() === $subjects)
            );
    }
}
