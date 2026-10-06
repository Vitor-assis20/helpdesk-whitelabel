<?php

namespace App\Notifications;

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
}
