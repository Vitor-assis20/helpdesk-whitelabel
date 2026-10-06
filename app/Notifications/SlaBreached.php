<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso à equipe de que um prazo de SLA estourou. Só escalares (fila).
 */
class SlaBreached extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly int $number,
        public readonly string $subject,
        public readonly string $target,
        public readonly string $ticketUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $what = $this->target === 'first_response' ? 'primeira resposta' : 'resolução';

        return (new MailMessage)
            ->subject("[{$this->tenantName}] Prazo de {$what} estourado no chamado #{$this->number}")
            ->greeting('Prazo de SLA estourado')
            ->line("O chamado #{$this->number} · {$this->subject} passou do prazo de {$what}.")
            ->action('Abrir chamado', $this->ticketUrl);
    }
}
