<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Convite para a equipe de atendimento. Só escalares (fila).
 */
class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $inviterName,
        public readonly string $roleLabel,
        public readonly string $acceptUrl,
        public readonly int $validForDays,
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
        return (new MailMessage)
            ->subject("{$this->inviterName} convidou você para a equipe de {$this->tenantName}")
            ->greeting('Você foi convidado!')
            ->line("{$this->inviterName} convidou você para atender os clientes de {$this->tenantName} como {$this->roleLabel}.")
            ->action('Aceitar convite', $this->acceptUrl)
            ->line("O convite vale por {$this->validForDays} dias. Se não esperava por ele, é só ignorar este e-mail.");
    }
}
