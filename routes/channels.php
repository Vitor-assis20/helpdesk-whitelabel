<?php

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Canais da equipe. Só entra quem faz parte da equipe da empresa do canal.
| As consultas dispensam o TenantScope de propósito: aqui não há empresa no
| contexto, e a pertinência é exatamente o que está sendo verificado.
*/

$isTeamMember = fn (User $user, ?int $tenantId) => $tenantId !== null
    && Tenant::query()->whereKey($tenantId)->whereHas('users', fn ($query) => $query->whereKey($user->id))->exists();

$tenantOfTicket = fn (int $ticketId) => Ticket::withoutGlobalScopes()->whereKey($ticketId)->value('tenant_id');

// Caixa de entrada da empresa.
Broadcast::channel('tenants.{tenantId}.tickets', fn (User $user, int $tenantId) => $isTeamMember($user, $tenantId));

// Conversa de um chamado.
Broadcast::channel('tickets.{ticketId}', fn (User $user, int $ticketId) => $isTeamMember($user, $tenantOfTicket($ticketId)));

// Presença: quem está com o chamado aberto (e "digitando…" via whisper).
Broadcast::channel('tickets.{ticketId}.viewers', fn (User $user, int $ticketId) => $isTeamMember($user, $tenantOfTicket($ticketId))
    ? ['id' => $user->id, 'name' => $user->name]
    : false);
