<?php

namespace App\Notifications;

use App\Enums\SatisfactionScore;

class TicketResolved extends TicketNotification
{
    protected function subject(): string
    {
        return 'Chamado resolvido';
    }

    protected function title(): string
    {
        return 'Seu chamado foi resolvido';
    }

    protected function intro(): string
    {
        return 'Marcamos este chamado como resolvido. Se ainda precisar de ajuda, é só responder pelo link: ele volta para a equipe.';
    }

    protected function outro(): ?string
    {
        return null;
    }

    /**
     * Cada nota abre o chamado com ela pré-selecionada; o cliente confirma lá.
     */
    protected function ratingLinks(): ?array
    {
        return collect(SatisfactionScore::cases())
            ->mapWithKeys(fn (SatisfactionScore $score) => ["{$score->value} · {$score->label()}" => $this->summary->ticketUrl.'?nota='.$score->value.'#avaliacao'])
            ->all();
    }
}
