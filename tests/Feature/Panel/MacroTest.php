<?php

namespace Tests\Feature\Panel;

use App\Enums\TenantRole;
use App\Helpdesk\MacroRenderer;
use App\Models\Macro;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Helpdesk\HelpdeskTestCase;

class MacroTest extends HelpdeskTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->agent->update(['name' => 'Ana Souza']);
    }

    public function test_fills_the_variables_with_the_ticket_data()
    {
        $ticket = $this->openTicket(subject: 'Troca de tamanho');

        $body = app(MacroRenderer::class)->render(
            "Olá, {{cliente.primeiro_nome}}! Sobre o #{{ chamado.numero }} ({{chamado.assunto}}):\n{{atendente.primeiro_nome}}, {{empresa.nome}} · {{cliente.email}}",
            $ticket,
            $this->agent,
        );

        $this->assertSame("Olá, Marina! Sobre o #{$ticket->number} (Troca de tamanho):\nAna, Loja Aurora · {$this->customer->email}", $body);
    }

    public function test_first_names_skip_honorifics()
    {
        $this->customer->update(['name' => 'Dra. Marina Costa']);
        $this->agent->update(['name' => '  Ana  ']);

        $body = app(MacroRenderer::class)->render('{{cliente.primeiro_nome}}/{{atendente.primeiro_nome}}', $this->openTicket(), $this->agent);

        $this->assertSame('Marina/Ana', $body);
    }

    public function test_unknown_variables_stay_visible_for_the_agent_to_fix()
    {
        $body = app(MacroRenderer::class)->render('Olá {{cliente.apelido}} {{ nada }} {cliente.nome}', $this->openTicket(), $this->agent);

        $this->assertSame('Olá {{cliente.apelido}} {{ nada }} {cliente.nome}', $body);
    }

    public function test_the_ticket_page_offers_the_macros_filled_in_most_used_first()
    {
        $this->tenant->run(function () {
            Macro::create(['title' => 'Boas-vindas', 'body' => 'Oi, {{cliente.primeiro_nome}}!']);
            Macro::create(['title' => 'Prazo', 'body' => 'Chega em 5 dias.'])->forceFill(['uses' => 3])->save();
        });
        Tenant::factory()->create()->run(fn () => Macro::create(['title' => 'De outra empresa', 'body' => 'x']));

        $ticket = $this->openTicket();

        $this->actingAs($this->agent)
            ->get("/painel/chamados/{$ticket->number}")
            ->assertInertia(fn (Assert $page) => $page
                ->has('macros', 2)
                ->where('macros.0.title', 'Prazo')
                ->where('macros.1.body', 'Oi, Marina!')
            );
    }

    public function test_replying_with_a_macro_counts_its_use_only_inside_the_company()
    {
        $mine = $this->tenant->run(fn () => Macro::create(['title' => 'Prazo', 'body' => 'Chega em 5 dias.']));
        $theirs = Tenant::factory()->create()->run(fn () => Macro::create(['title' => 'Prazo', 'body' => 'x']));
        $ticket = $this->openTicket();

        $this->actingAs($this->agent)
            ->post("/painel/chamados/{$ticket->number}/mensagens", ['body' => 'Chega em 5 dias.', 'macro_id' => $mine->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->agent)
            ->post("/painel/chamados/{$ticket->number}/mensagens", ['body' => 'Outra.', 'macro_id' => $theirs->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Macro::withoutGlobalScopes()->find($mine->id)->uses);
        $this->assertSame(0, Macro::withoutGlobalScopes()->find($theirs->id)->uses);
    }

    public function test_admins_manage_the_macros_and_agents_only_use_them()
    {
        $this->actingAs($this->owner)
            ->post('/painel/respostas', ['title' => 'Prazo', 'body' => 'Chega em {{chamado.numero}} dias.'])
            ->assertSessionHasNoErrors();
        $macro = $this->tenant->run(fn () => Macro::sole());

        $this->actingAs($this->owner)
            ->get('/painel/respostas')
            ->assertInertia(fn (Assert $page) => $page
                ->component('panel/macros')
                ->where('macros.0.body', 'Chega em {{chamado.numero}} dias.')
                ->where('variables.0', ['name' => 'cliente.nome', 'label' => 'Nome do cliente'])
            );

        $this->actingAs($this->owner)->put("/painel/respostas/{$macro->id}", ['title' => 'Prazo de entrega', 'body' => 'Novo texto'])->assertSessionHasNoErrors();
        $this->assertSame('Prazo de entrega', $macro->refresh()->title);

        $this->actingAs($this->agent)->get('/painel/respostas')->assertForbidden();
        $this->actingAs($this->agent)->post('/painel/respostas', ['title' => 'X', 'body' => 'Y'])->assertForbidden();
        $this->actingAs($this->agent)->delete("/painel/respostas/{$macro->id}")->assertForbidden();

        $this->actingAs($this->owner)->delete("/painel/respostas/{$macro->id}")->assertRedirect();
        $this->assertSame(0, Macro::withoutGlobalScopes()->count());
    }

    public function test_titles_are_unique_per_company_ignoring_case()
    {
        $this->tenant->run(fn () => Macro::create(['title' => 'Prazo', 'body' => 'x']));

        $this->actingAs($this->owner)
            ->post('/painel/respostas', ['title' => 'PRAZO', 'body' => 'y'])
            ->assertSessionHasErrors(['title' => 'Já existe uma resposta com esse título.']);

        // Editar mantendo o próprio título não conflita.
        $macro = $this->tenant->run(fn () => Macro::sole());
        $this->actingAs($this->owner)->put("/painel/respostas/{$macro->id}", ['title' => 'prazo', 'body' => 'z'])->assertSessionHasNoErrors();

        // Outra empresa pode ter o mesmo título.
        $other = User::factory()->create();
        Tenant::factory()->withUser($other, TenantRole::Owner)->create();
        $this->actingAs($other)->post('/painel/respostas', ['title' => 'Prazo', 'body' => 'y'])->assertSessionHasNoErrors();

        // O banco garante a mesma regra.
        $this->assertRejectedByDatabase('23505', fn () => $this->tenant->run(fn () => Macro::create(['title' => 'PRAZO', 'body' => 'y'])));
    }

    public function test_macros_of_other_companies_cannot_be_edited()
    {
        $theirs = Tenant::factory()->create()->run(fn () => Macro::create(['title' => 'Prazo', 'body' => 'x']));

        $this->actingAs($this->owner)->put("/painel/respostas/{$theirs->id}", ['title' => 'Meu', 'body' => 'y'])->assertNotFound();
        $this->actingAs($this->owner)->delete("/painel/respostas/{$theirs->id}")->assertNotFound();

        $this->assertSame('Prazo', Macro::withoutGlobalScopes()->find($theirs->id)->title);
    }

    public function test_blank_macros_are_rejected_by_the_database()
    {
        $this->assertRejectedByDatabase('23514', fn () => $this->tenant->run(fn () => Macro::create(['title' => '  ', 'body' => 'x'])));
        $this->assertRejectedByDatabase('23514', fn () => $this->tenant->run(fn () => Macro::create(['title' => 'Prazo', 'body' => "\n"])));
    }
}
