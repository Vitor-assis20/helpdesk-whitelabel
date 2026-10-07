<?php

namespace App\Helpdesk;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Tenancy\TenantScope;

/**
 * Preenche as variáveis de uma resposta pronta com os dados do chamado.
 *
 * Aceita espaços dentro das chaves ({{ cliente.nome }}). Variável
 * desconhecida fica como está, para o atendente ver e corrigir antes de
 * enviar. O resultado é texto puro: o front exibe com textContent.
 */
class MacroRenderer
{
    /**
     * Variáveis disponíveis e o que cada uma mostra (exibido na tela de edição).
     */
    public const VARIABLES = [
        'cliente.nome' => 'Nome do cliente',
        'cliente.primeiro_nome' => 'Primeiro nome do cliente',
        'cliente.email' => 'E-mail do cliente',
        'chamado.numero' => 'Número do chamado',
        'chamado.assunto' => 'Assunto do chamado',
        'atendente.nome' => 'Seu nome',
        'atendente.primeiro_nome' => 'Seu primeiro nome',
        'empresa.nome' => 'Nome da empresa',
    ];

    public function render(string $body, Ticket $ticket, User $agent): string
    {
        // O chamado já identifica o cliente: a busca dispensa a empresa no contexto.
        if (! $ticket->relationLoaded('customer')) {
            $ticket->setRelation('customer', $ticket->customer()->withoutGlobalScope(TenantScope::class)->first());
        }

        $customer = $ticket->customer;
        $values = [
            'cliente.nome' => $customer->name,
            'cliente.primeiro_nome' => $this->firstName($customer->name),
            'cliente.email' => $customer->email,
            'chamado.numero' => (string) $ticket->number,
            'chamado.assunto' => $ticket->subject,
            'atendente.nome' => $agent->name,
            'atendente.primeiro_nome' => $this->firstName($agent->name),
            'empresa.nome' => $ticket->tenant->name,
        ];

        return preg_replace_callback(
            '/\{\{\s*([a-z_]+\.[a-z_]+)\s*\}\}/',
            fn (array $match) => $values[$match[1]] ?? $match[0],
            $body,
        );
    }

    /**
     * Primeiro nome, pulando abreviações de tratamento ("Dr. João" → "João").
     */
    private function firstName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), flags: PREG_SPLIT_NO_EMPTY);

        return collect($words)->first(fn (string $word) => ! str_ends_with($word, '.')) ?? $words[0] ?? '';
    }
}
