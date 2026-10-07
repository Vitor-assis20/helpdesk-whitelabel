<?php

namespace App\Helpdesk\Team;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aceita o convite do link: o usuário entra na equipe com o papel convidado.
 *
 * Exige o token E a conta com o e-mail convidado. O token sozinho valeria
 * para qualquer um a quem o link fosse repassado; o e-mail sozinho não
 * prova nada, já que o cadastro não confirma o endereço.
 */
class AcceptInvitation
{
    public function handle(string $token, User $user): Tenant
    {
        return DB::transaction(function () use ($token, $user) {
            // Trava o convite: dois cliques simultâneos não aceitam duas vezes.
            $invitation = TenantInvitation::withoutGlobalScopes()
                ->where('token_hash', TenantInvitation::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || ! $invitation->isUsable()) {
                throw TeamActionFailed::invitationUnusable();
            }

            if (Str::lower($user->email) !== $invitation->email) {
                throw TeamActionFailed::wrongAccount($invitation->email);
            }

            $tenant = Tenant::query()->findOrFail($invitation->tenant_id);

            // Já na equipe (entrou por outro caminho): mantém o papel atual.
            $tenant->users()->syncWithoutDetaching([$user->id => ['role' => $tenant->roleOf($user)?->value ?? $invitation->role->value]]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $tenant;
        });
    }
}
