<?php

namespace App\Helpdesk;

use App\Enums\TicketStatus;
use DomainException;

class TicketActionFailed extends DomainException
{
    public static function closed(): self
    {
        return new self('Este chamado está encerrado. Abra um novo chamado para continuar o atendimento.');
    }

    public static function notMember(): self
    {
        return new self('Este usuário não faz parte da equipe desta empresa.');
    }

    public static function notOwner(): self
    {
        return new self('Este chamado pertence a outro cliente.');
    }

    public static function notRatable(): self
    {
        return new self('Você poderá avaliar o atendimento quando o chamado for resolvido.');
    }

    public static function invalidTransition(TicketStatus $from, TicketStatus $to): self
    {
        return new self("Um chamado \"{$from->label()}\" não pode passar para \"{$to->label()}\".");
    }
}
