<?php

namespace App\Notifications;

use App\Helpdesk\TicketMailSummary;

class AgentReplied extends TicketNotification
{
    public function __construct(TicketMailSummary $summary, public readonly string $agentName, public readonly string $reply)
    {
        parent::__construct($summary);
    }

    protected function subject(): string
    {
        return 'Nova resposta';
    }

    protected function title(): string
    {
        return 'Seu chamado tem uma nova resposta';
    }

    protected function intro(): string
    {
        return "{$this->agentName} respondeu:";
    }

    protected function quote(): string
    {
        return $this->reply;
    }

    protected function actionText(): string
    {
        return 'Ver e responder';
    }
}
