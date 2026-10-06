<?php

namespace App\Console\Commands;

use App\Enums\TenantRole;
use App\Events\TicketChanged;
use App\Models\Tenant;
use App\Models\TicketEvent;
use App\Models\User;
use App\Notifications\SlaBreached;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CheckSlaBreaches extends Command
{
    protected $signature = 'tickets:check-sla';

    protected $description = 'Marca e avisa os chamados cujo prazo de SLA estourou';

    /**
     * Para cada prazo: a condição de "ainda pendente" e as colunas envolvidas.
     */
    private const TARGETS = [
        'first_response' => ['due' => 'first_response_due_at', 'breached' => 'first_response_breached_at', 'pending' => 'first_response_at IS NULL'],
        'resolution' => ['due' => 'resolution_due_at', 'breached' => 'resolution_breached_at', 'pending' => 'resolved_at IS NULL'],
    ];

    public function handle(): int
    {
        $total = 0;

        foreach (self::TARGETS as $target => $columns) {
            // Marca e devolve numa operação só: duas execuções simultâneas nunca
            // pegam o mesmo chamado, então ninguém é avisado duas vezes.
            // Atravessa todas as empresas de propósito (SQL direto, sem TenantScope).
            $breached = DB::select(<<<SQL
                UPDATE tickets SET {$columns['breached']} = ?
                WHERE {$columns['breached']} IS NULL
                  AND {$columns['pending']}
                  AND status IN ('new', 'open', 'pending')
                  AND {$columns['due']} <= ?
                RETURNING id, tenant_id, number, subject, assignee_id
            SQL, [now()->utc()->format('Y-m-d H:i:s'), now()->utc()->format('Y-m-d H:i:s')]);

            foreach ($breached as $ticket) {
                $this->announce($ticket, $target);
            }

            $total += count($breached);
        }

        $this->info("{$total} prazo(s) estourado(s).");

        return self::SUCCESS;
    }

    private function announce(object $ticket, string $target): void
    {
        $event = new TicketEvent(['ticket_id' => $ticket->id, 'type' => 'sla_breached', 'data' => ['target' => $target]]);
        $event->tenant_id = $ticket->tenant_id;
        $event->save();

        TicketChanged::dispatch($ticket->tenant_id, $ticket->id, $ticket->number, TicketChanged::SLA);

        $tenant = Tenant::findOrFail($ticket->tenant_id);
        Notification::send($this->recipients($tenant, $ticket->assignee_id), new SlaBreached(
            tenantName: $tenant->name,
            number: $ticket->number,
            subject: $ticket->subject,
            target: $target,
            ticketUrl: route('panel.tickets.show', ['ticket' => $ticket->number]),
        ));
    }

    /**
     * O responsável; sem responsável, quem gerencia a empresa.
     *
     * @return iterable<User>
     */
    private function recipients(Tenant $tenant, ?int $assigneeId): iterable
    {
        if ($assigneeId) {
            return User::whereKey($assigneeId)->get();
        }

        return $tenant->users()->wherePivotIn('role', [TenantRole::Owner->value, TenantRole::Admin->value])->get();
    }
}
