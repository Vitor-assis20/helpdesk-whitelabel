<?php

namespace App\Enums;

/**
 * Ciclo de vida do chamado.
 *
 * new       aberto pelo cliente, ainda sem ação da equipe
 * open      em atendimento
 * pending   aguardando o cliente (a equipe respondeu)
 * resolved  resolvido; uma resposta do cliente reabre
 * closed    encerrado de vez
 */
enum TicketStatus: string
{
    case New = 'new';
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * Mudanças manuais permitidas a partir deste status.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::New => [self::Open, self::Pending, self::Resolved, self::Closed],
            self::Open => [self::Pending, self::Resolved, self::Closed],
            self::Pending => [self::Open, self::Resolved, self::Closed],
            self::Resolved => [self::Open, self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->transitions(), true);
    }

    /**
     * Chamado que ainda pede ação da equipe ou do cliente.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::New, self::Open, self::Pending], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::New => 'Novo',
            self::Open => 'Em atendimento',
            self::Pending => 'Aguardando cliente',
            self::Resolved => 'Resolvido',
            self::Closed => 'Encerrado',
        };
    }
}
