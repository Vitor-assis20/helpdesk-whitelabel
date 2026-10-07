<?php

namespace Tests\Feature\Panel;

use App\Enums\TenantRole;
use App\Helpdesk\AssignTicket;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Helpdesk\HelpdeskTestCase;

class TeamTest extends HelpdeskTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();

        $this->admin = User::factory()->create(['name' => 'Carlos']);
        $this->tenant->users()->attach($this->admin, ['role' => TenantRole::Admin->value]);
    }

    public function test_lists_the_team_and_what_each_role_can_manage()
    {
        $this->invite('nova@example.com', 'agent', $this->owner);

        $this->actingAs($this->admin)
            ->get('/painel/equipe')
            ->assertInertia(fn (Assert $page) => $page
                ->component('panel/team')
                ->has('members', 3)
                // O administrador gerencia atendentes; nem o dono nem a si mesmo.
                ->where('members', fn ($members) => collect($members)->pluck('manageable', 'role')->all() == ['admin' => false, 'agent' => true, 'owner' => false])
                ->has('invitations', 1)
                ->where('invitations.0.email', 'nova@example.com')
                ->where('invitations.0.invited_by', $this->owner->name)
                ->where('roles', [['value' => 'agent', 'label' => 'Atendente']])
            );

        $this->actingAs($this->agent)->get('/painel/equipe')->assertForbidden();
    }

    public function test_invites_by_email_storing_only_the_hash_of_the_token()
    {
        $token = $this->invite('Nova@Example.com ', 'admin', $this->owner);

        $invitation = TenantInvitation::withoutGlobalScopes()->sole();
        $this->assertSame('nova@example.com', $invitation->email);
        $this->assertSame(TenantRole::Admin, $invitation->role);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertTrue($invitation->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)));
    }

    public function test_inviting_again_renews_the_pending_invitation_and_kills_the_old_link()
    {
        $old = $this->invite('nova@example.com', 'agent', $this->owner);
        $new = $this->invite('nova@example.com', 'admin', $this->owner);

        $this->assertNotSame($old, $new);
        $this->assertSame(1, TenantInvitation::withoutGlobalScopes()->count());
        $this->assertNull(TenantInvitation::findByToken($old));
        $this->assertSame(TenantRole::Admin, TenantInvitation::findByToken($new)->role);
    }

    public function test_only_the_owner_invites_admins_and_nobody_invites_current_members()
    {
        $this->actingAs($this->admin)
            ->post('/painel/equipe/convites', ['email' => 'nova@example.com', 'role' => 'admin'])
            ->assertSessionHasErrors(['email' => 'Só o dono da empresa gerencia administradores.']);

        $this->actingAs($this->owner)
            ->post('/painel/equipe/convites', ['email' => strtoupper($this->agent->email), 'role' => 'agent'])
            ->assertSessionHasErrors(['email' => 'Essa pessoa já faz parte da equipe.']);

        // Dono não se convida.
        $this->actingAs($this->owner)
            ->post('/painel/equipe/convites', ['email' => 'nova@example.com', 'role' => 'owner'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_a_guest_signs_up_from_the_invitation_and_joins_the_team()
    {
        $token = $this->invite('nova@example.com', 'agent', $this->owner);

        $this->get("/convites/{$token}")->assertInertia(fn (Assert $page) => $page
            ->component('invitation')
            ->where('state', 'guest')
            ->where('invitation.tenant', 'Loja Aurora')
            ->where('invitation.role_label', 'Atendente')
        );

        // O cadastro volta para o convite...
        $this->get('/register?email=nova@example.com')->assertInertia(fn (Assert $page) => $page->where('email', 'nova@example.com'));
        $this->post('/register', [
            'name' => 'Nova',
            'email' => 'nova@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect("/convites/{$token}");

        $this->get("/convites/{$token}")->assertInertia(fn (Assert $page) => $page->where('state', 'ready'));

        // ...e aceitar abre o painel já na empresa.
        $this->post("/convites/{$token}")->assertRedirect('/painel/chamados');

        $user = User::firstWhere('email', 'nova@example.com');
        $this->assertSame(TenantRole::Agent, $this->tenant->roleOf($user));
        $this->get('/painel/chamados')->assertInertia(fn (Assert $page) => $page->where('panel.current.id', $this->tenant->id));

        // O link vale uma vez só.
        $this->get("/convites/{$token}")->assertInertia(fn (Assert $page) => $page->where('state', 'invalid')->where('invitation', null));
        $this->post("/convites/{$token}")->assertSessionHasErrors('invitation');
    }

    public function test_the_link_only_works_for_the_invited_account()
    {
        $token = $this->invite('nova@example.com', 'agent', $this->owner);
        $other = User::factory()->create(['email' => 'outra@example.com']);

        $this->actingAs($other)->get("/convites/{$token}")->assertInertia(fn (Assert $page) => $page->where('state', 'wrong_account'));
        $this->actingAs($other)
            ->post("/convites/{$token}")
            ->assertSessionHasErrors(['invitation' => 'Este convite foi enviado para nova@example.com. Entre com essa conta para aceitar.']);

        $this->assertFalse($this->tenant->hasMember($other));
    }

    public function test_expired_and_unknown_links_are_refused()
    {
        $token = $this->invite('nova@example.com', 'agent', $this->owner);
        $user = User::factory()->create(['email' => 'nova@example.com']);

        $this->travel(TenantInvitation::VALID_FOR_DAYS + 1)->days();

        $this->actingAs($user)->get("/convites/{$token}")->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
        $this->actingAs($user)->post("/convites/{$token}")->assertSessionHasErrors('invitation');
        $this->actingAs($user)->post('/convites/'.str_repeat('a', 40))->assertSessionHasErrors('invitation');

        $this->assertFalse($this->tenant->hasMember($user));
    }

    public function test_invitations_are_revoked_only_inside_the_company()
    {
        $this->invite('nova@example.com', 'agent', $this->owner);
        $invitation = TenantInvitation::withoutGlobalScopes()->sole();

        $outsider = User::factory()->create();
        Tenant::factory()->withUser($outsider, TenantRole::Owner)->create();

        $this->actingAs($outsider)->delete("/painel/equipe/convites/{$invitation->id}")->assertNotFound();
        $this->actingAs($this->owner)->delete("/painel/equipe/convites/{$invitation->id}")->assertRedirect();

        $this->assertSame(0, TenantInvitation::withoutGlobalScopes()->count());
    }

    public function test_roles_change_only_down_the_hierarchy()
    {
        $this->actingAs($this->owner)->patch("/painel/equipe/membros/{$this->agent->id}", ['role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame(TenantRole::Admin, $this->tenant->roleOf($this->agent));

        // Administrador não mexe em administrador, no dono nem em si mesmo.
        $this->actingAs($this->admin)
            ->patch("/painel/equipe/membros/{$this->agent->id}", ['role' => 'agent'])
            ->assertSessionHasErrors(['member' => 'Só o dono da empresa gerencia administradores.']);
        $this->actingAs($this->admin)
            ->patch("/painel/equipe/membros/{$this->owner->id}", ['role' => 'agent'])
            ->assertSessionHasErrors(['member' => 'O dono da empresa não pode ser alterado nem removido.']);
        $this->actingAs($this->owner)
            ->patch("/painel/equipe/membros/{$this->owner->id}", ['role' => 'agent'])
            ->assertSessionHasErrors(['member' => 'Você não pode alterar o próprio acesso. Peça a outro administrador.']);

        // Nem o dono cria outro dono.
        $this->actingAs($this->owner)->patch("/painel/equipe/membros/{$this->admin->id}", ['role' => 'owner'])->assertSessionHasErrors('member');

        $this->assertSame(TenantRole::Owner, $this->tenant->roleOf($this->owner));
        $this->assertSame(TenantRole::Admin, $this->tenant->roleOf($this->admin));
    }

    public function test_members_of_other_companies_cannot_be_touched()
    {
        $stranger = User::factory()->create();
        Tenant::factory()->withUser($stranger, TenantRole::Agent)->create();

        $this->actingAs($this->owner)
            ->delete("/painel/equipe/membros/{$stranger->id}")
            ->assertSessionHasErrors(['member' => 'Este usuário não faz parte da equipe desta empresa.']);

        $this->assertSame(1, $stranger->tenants()->count());
    }

    public function test_removing_a_member_unassigns_their_tickets_and_closes_the_panel()
    {
        $ticket = $this->openTicket();
        app(AssignTicket::class)->handle($ticket, $this->agent, $this->owner);

        $this->actingAs($this->admin)->delete("/painel/equipe/membros/{$this->agent->id}")->assertSessionHasNoErrors();

        $this->assertFalse($this->tenant->hasMember($this->agent));
        $this->assertNull($ticket->refresh()->assignee_id);

        $this->actingAs($this->agent)->get('/painel/chamados')->assertInertia(fn (Assert $page) => $page->component('panel/no-business'));
    }

    public function test_someone_without_a_team_registers_a_company_and_becomes_its_owner()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/painel/chamados')->assertInertia(fn (Assert $page) => $page
            ->component('panel/no-business')
            ->where('domain', config('app.domain'))
        );

        $this->actingAs($user)
            ->post('/painel/empresas', ['name' => 'Café Brasa', 'slug' => 'cafe-brasa'])
            ->assertRedirect('/painel/configuracoes');

        $tenant = Tenant::firstWhere('slug', 'cafe-brasa');
        $this->assertSame('Café Brasa', $tenant->name);
        $this->assertSame(TenantRole::Owner, $tenant->roleOf($user));
        $this->actingAs($user)->get('/painel/configuracoes')->assertInertia(fn (Assert $page) => $page->where('business.slug', 'cafe-brasa'));
    }

    public function test_company_addresses_must_be_valid_free_and_not_reserved()
    {
        $user = User::factory()->create();
        $this->tenant->update(['slug' => 'aurora']);

        // Cinco tentativas: a rota aceita cinco cadastros por minuto.
        foreach (['Café', 'cafe--brasa', 'www', 'aurora', str_repeat('a', 64)] as $slug) {
            $this->actingAs($user)->post('/painel/empresas', ['name' => 'Café', 'slug' => $slug])->assertSessionHasErrors('slug');
        }

        $this->assertSame(0, $user->tenants()->count());
    }

    public function test_the_database_keeps_invitations_consistent()
    {
        $row = fn (array $overrides = []) => [
            'tenant_id' => $this->tenant->id,
            'email' => 'nova@example.com',
            'role' => 'agent',
            'token_hash' => hash('sha256', uniqid()),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ];

        // Ninguém é convidado para dono, e o e-mail é gravado em minúsculas.
        $this->assertRejectedByDatabase('23514', fn () => TenantInvitation::withoutGlobalScopes()->insert($row(['role' => 'owner'])));
        $this->assertRejectedByDatabase('23514', fn () => TenantInvitation::withoutGlobalScopes()->insert($row(['email' => 'Nova@example.com'])));

        // Um convite pendente por e-mail na empresa; aceitos não contam.
        TenantInvitation::withoutGlobalScopes()->insert($row(['accepted_at' => now()]));
        TenantInvitation::withoutGlobalScopes()->insert($row());
        $this->assertRejectedByDatabase('23505', fn () => TenantInvitation::withoutGlobalScopes()->insert($row()));
    }

    /**
     * Convida pela tela e devolve o token que foi no e-mail.
     */
    private function invite(string $email, string $role, User $by): string
    {
        $this->actingAs($by)
            ->post('http://localhost/painel/equipe/convites', ['email' => $email, 'role' => $role])
            ->assertSessionHasNoErrors();

        $token = null;
        // O último convite enviado para o e-mail (reenviar troca o token).
        Notification::assertSentOnDemand(TeamInvitation::class, function (TeamInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$token) {
            if ($notifiable->routes['mail'] !== strtolower(trim($email))) {
                return false;
            }

            $token = basename(parse_url($notification->acceptUrl, PHP_URL_PATH));

            return true;
        });

        $this->app['auth']->forgetGuards();

        return $token;
    }
}
