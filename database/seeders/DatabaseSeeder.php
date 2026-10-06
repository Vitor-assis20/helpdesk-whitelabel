<?php

namespace Database\Seeders;

use App\Enums\TenantRole;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Helpdesk\AssignTicket;
use App\Helpdesk\ChangeTicketStatus;
use App\Helpdesk\OpenTicket;
use App\Helpdesk\ReplyToTicket;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Chamados de demonstração: [assunto, mensagem, prioridade, até onde a conversa vai].
     */
    private const STORE_TICKETS = [
        ['Pedido não chegou', 'Comprei há 10 dias e o rastreio não atualiza desde sexta.', TicketPriority::High, 'pending'],
        ['Cobrança em duplicidade', 'Apareceram duas cobranças do mesmo pedido no meu cartão.', TicketPriority::Urgent, 'open'],
        ['Troca de tamanho', 'O tênis ficou pequeno, quero trocar por um 42.', TicketPriority::Normal, 'resolved'],
        ['Cupom não aplicou', 'Usei o cupom BEMVINDO10 mas o desconto não apareceu.', TicketPriority::Low, 'new'],
        ['Produto com defeito', 'A alça da mochila descosturou no primeiro uso.', TicketPriority::Normal, 'open'],
        ['Alterar endereço de entrega', 'Preciso mudar o endereço, o pedido ainda não saiu.', TicketPriority::High, 'new'],
        ['Nota fiscal', 'Não recebi a nota fiscal da minha compra.', TicketPriority::Low, 'closed'],
        ['Cancelar pedido', 'Quero cancelar o pedido 48213, comprei por engano.', TicketPriority::Normal, 'pending'],
        ['Prazo de entrega', 'Qual o prazo para entrega em Florianópolis?', TicketPriority::Low, 'resolved'],
        ['Reembolso não caiu', 'O estorno foi aprovado há 15 dias e ainda não caiu.', TicketPriority::High, 'new'],
    ];

    private const CLINIC_TICKETS = [
        ['Remarcar consulta', 'Preciso remarcar minha consulta de quinta para a próxima semana.', TicketPriority::Normal, 'pending'],
        ['Resultado de exame', 'Meu exame de sangue já está disponível?', TicketPriority::Normal, 'new'],
        ['Reembolso do plano', 'Preciso da declaração para pedir reembolso ao plano.', TicketPriority::Low, 'open'],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
        $ana = User::factory()->create(['name' => 'Ana Martins', 'email' => 'ana@example.com']);
        $carlos = User::factory()->create(['name' => 'Carlos Souza', 'email' => 'carlos@example.com']);

        $store = Tenant::factory()
            ->withUser($owner, TenantRole::Owner)
            ->withUser($carlos, TenantRole::Admin)
            ->withUser($ana, TenantRole::Agent)
            ->create(['name' => 'Loja Aurora', 'slug' => 'loja-aurora', 'branding' => ['primary_color' => '#7c3aed', 'logo_path' => null]]);

        $clinic = Tenant::factory()
            ->withUser($owner, TenantRole::Owner)
            ->create(['name' => 'Clínica Bem Estar', 'slug' => 'clinica-bem-estar', 'branding' => ['primary_color' => '#0f766e', 'logo_path' => null]]);

        $this->seedTickets($store, self::STORE_TICKETS, agents: [$ana, $carlos], manager: $owner);
        $this->seedTickets($clinic, self::CLINIC_TICKETS, agents: [$owner], manager: $owner);

        // Marca os prazos que já estouraram nos dias simulados, como o agendador faria.
        $this->command->call('tickets:check-sla');
    }

    /**
     * Cria os chamados pelas ações de domínio, com o relógio voltado no tempo,
     * para que histórico, números e datas fiquem coerentes.
     *
     * @param  list<array{0: string, 1: string, 2: TicketPriority, 3: string}>  $tickets
     * @param  list<User>  $agents
     */
    private function seedTickets(Tenant $tenant, array $tickets, array $agents, User $manager): void
    {
        $customers = Customer::factory()->count(8)->for($tenant)->create();
        $reply = app(ReplyToTicket::class);

        foreach ($tickets as $index => [$subject, $body, $priority, $stage]) {
            $agent = $agents[$index % count($agents)];
            $customer = $customers[$index % $customers->count()];
            // Imutável: cada passo da conversa conta a partir da abertura.
            $clock = CarbonImmutable::now()->subHours(72 - $index * 6);

            $ticket = $this->at($clock, fn () => app(OpenTicket::class)->handle(
                $customer, $subject, $body, fake()->randomElement([TicketChannel::Portal, TicketChannel::Widget]), $priority,
            ));

            if ($stage === 'new') {
                continue;
            }

            $this->at($clock->addMinutes(40), fn () => app(AssignTicket::class)->handle($ticket, $agent, $manager));
            $this->at($clock->addMinutes(55), fn () => $reply->asAgent($ticket, $agent, 'Cliente com histórico de compras recorrentes.', internal: true));

            if ($stage === 'open') {
                continue;
            }

            $this->at($clock->addHours(2), fn () => $reply->asAgent($ticket, $agent, "Olá, {$customer->name}! Já estamos verificando. Pode me enviar o número do pedido?"));

            if ($stage === 'pending') {
                continue;
            }

            $this->at($clock->addHours(5), fn () => $reply->asCustomer($ticket, $customer, 'Claro, segue o número: 48213.'));
            $this->at($clock->addHours(6), fn () => $reply->asAgent($ticket, $agent, 'Tudo certo, resolvido! Qualquer coisa é só responder aqui.'));
            $this->at($clock->addHours(6), fn () => app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Resolved, $agent));

            if ($stage === 'closed') {
                $this->at($clock->addHours(30), fn () => app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Closed, $agent));
            }
        }
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function at(CarbonImmutable $time, callable $callback): mixed
    {
        Carbon::setTestNow($time);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
