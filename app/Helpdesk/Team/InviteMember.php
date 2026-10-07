<?php

namespace App\Helpdesk\Team;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Convida alguém para a equipe da empresa.
 *
 * Convidar de novo o mesmo e-mail renova o convite pendente: gera outro
 * link (o anterior deixa de valer), atualiza o papel e o prazo.
 */
class InviteMember
{
    public function handle(Tenant $tenant, User $inviter, TenantRole $inviterRole, string $email, TenantRole $role): TenantInvitation
    {
        if (! in_array($role, TenantRole::assignable(), true)) {
            throw TeamActionFailed::notAssignable($role);
        }

        if (! $inviterRole->canManage($role)) {
            throw TeamActionFailed::cannotManage($role);
        }

        $email = Str::lower(trim($email));

        if ($tenant->users()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw TeamActionFailed::alreadyMember();
        }

        $token = Str::random(40);

        $invitation = DB::transaction(function () use ($tenant, $inviter, $email, $role, $token) {
            $invitation = TenantInvitation::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pending()->where('email', $email)->lockForUpdate()->first()
                ?? new TenantInvitation(['email' => $email]);

            $invitation->tenant_id = $tenant->id;
            $invitation->fill([
                'role' => $role,
                'token_hash' => TenantInvitation::hashToken($token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(TenantInvitation::VALID_FOR_DAYS),
            ])->save();

            return $invitation;
        });

        // Só escalares na notificação: ela passa pela fila. O token só existe aqui e no e-mail.
        Notification::route('mail', $email)->notify(new TeamInvitation(
            tenantName: $tenant->name,
            inviterName: $inviter->name,
            roleLabel: $role->label(),
            acceptUrl: route('invitations.show', ['token' => $token]),
            validForDays: TenantInvitation::VALID_FOR_DAYS,
        ));

        return $invitation;
    }
}
