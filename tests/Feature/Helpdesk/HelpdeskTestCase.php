<?php

namespace Tests\Feature\Helpdesk;

use App\Enums\TenantRole;
use App\Enums\TicketChannel;
use App\Helpdesk\OpenTicket;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cenário comum: uma empresa com dono, um atendente e um cliente.
 */
abstract class HelpdeskTestCase extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected User $agent;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->agent = User::factory()->create();
        $this->tenant = Tenant::factory()
            ->withUser($this->owner, TenantRole::Owner)
            ->withUser($this->agent, TenantRole::Agent)
            ->create(['name' => 'Loja Aurora']);
        $this->customer = Customer::factory()->for($this->tenant)->create(['name' => 'Marina Costa']);
    }

    protected function openTicket(?Customer $customer = null, string $subject = 'Pedido não chegou'): Ticket
    {
        return app(OpenTicket::class)->handle($customer ?? $this->customer, $subject, 'Comprei há 10 dias e nada.', TicketChannel::Portal);
    }

    /**
     * Roda o callback num savepoint: no Postgres, um erro aborta a transação
     * inteira, e o RefreshDatabase já envolve o teste numa transação.
     */
    protected function assertRejectedByDatabase(string $sqlState, callable $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (QueryException $e) {
            $this->assertSame($sqlState, $e->getCode(), $e->getMessage());

            return;
        }

        $this->fail("O banco deveria ter rejeitado a operação com SQLSTATE {$sqlState}.");
    }
}
