<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Algo mudou num chamado. Vai para a caixa de entrada da empresa e para quem
 * está com o chamado aberto.
 *
 * O payload não leva conteúdo (mensagem, nota, dados do cliente): quem recebe
 * recarrega pelo servidor, que aplica permissões e filtros. Só escalares,
 * sem models: o broadcast passa pela fila, onde não há empresa no contexto.
 * Disparado só depois do commit, para ninguém recarregar dados que não existem.
 */
class TicketChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const CREATED = 'created';

    public const MESSAGE = 'message';

    public const STATUS = 'status';

    public const ASSIGNMENT = 'assignment';

    public function __construct(
        public readonly int $tenantId,
        public readonly int $ticketId,
        public readonly int $number,
        public readonly string $change,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenants.{$this->tenantId}.tickets"),
            new PrivateChannel("tickets.{$this->ticketId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ticket.changed';
    }

    /**
     * @return array{ticket_id: int, number: int, change: string}
     */
    public function broadcastWith(): array
    {
        return ['ticket_id' => $this->ticketId, 'number' => $this->number, 'change' => $this->change];
    }
}
