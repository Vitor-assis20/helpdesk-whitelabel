<?php

namespace App\Notifications;

use App\Helpdesk\TicketMailSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * E-mail ao cliente sobre o chamado, com a marca da empresa.
 *
 * Carrega um TicketMailSummary (dados prontos), nunca models.
 */
abstract class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly TicketMailSummary $summary) {}

    abstract protected function subject(): string;

    abstract protected function title(): string;

    abstract protected function intro(): string;

    protected function quote(): ?string
    {
        return null;
    }

    protected function actionText(): string
    {
        return 'Ver chamado';
    }

    protected function outro(): ?string
    {
        return 'Para responder, use o link acima.';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from(config('mail.from.address'), $this->summary->tenantName)
            ->subject("[#{$this->summary->number}] {$this->subject()} · {$this->summary->tenantName}")
            ->markdown('mail.ticket', [
                'summary' => $this->summary,
                'title' => $this->title(),
                'intro' => $this->intro(),
                'quote' => $this->quote() !== null ? Str::limit($this->quote(), 500) : null,
                'actionText' => $this->actionText(),
                'outro' => $this->outro(),
            ]);
    }
}
