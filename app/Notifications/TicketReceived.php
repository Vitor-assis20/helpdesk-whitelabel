<?php

namespace App\Notifications;

class TicketReceived extends TicketNotification
{
    protected function subject(): string
    {
        return 'Recebemos seu chamado';
    }

    protected function title(): string
    {
        return 'Recebemos seu chamado';
    }

    protected function intro(): string
    {
        return 'Nossa equipe já foi avisada e responde por aqui. Guarde este e-mail: o link abaixo leva à conversa.';
    }

    protected function actionText(): string
    {
        return 'Acompanhar chamado';
    }
}
