<?php

namespace App\Helpdesk\Team;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Troca de papel e remoção de membros da equipe.
 *
 * Ninguém mexe no próprio acesso nem no do dono, e só se gerencia quem está
 * abaixo na hierarquia (ver TenantRole::canManage).
 */
class ManageTeam
{
    public function changeRole(Tenant $tenant, User $actor, TenantRole $actorRole, User $member, TenantRole $role): void
    {
        if (! in_array($role, TenantRole::assignable(), true)) {
            throw TeamActionFailed::notAssignable($role);
        }

        DB::transaction(function () use ($tenant, $actor, $actorRole, $member, $role) {
            $this->authorize($tenant, $actor, $actorRole, $member);

            if (! $actorRole->canManage($role)) {
                throw TeamActionFailed::cannotManage($role);
            }

            $tenant->users()->updateExistingPivot($member->id, ['role' => $role->value]);
        });
    }

    /**
     * Os chamados do membro ficam sem responsável: a FK composta para
     * tenant_user faz isso no banco (ON DELETE SET NULL (assignee_id)).
     */
    public function remove(Tenant $tenant, User $actor, TenantRole $actorRole, User $member): void
    {
        DB::transaction(function () use ($tenant, $actor, $actorRole, $member) {
            $this->authorize($tenant, $actor, $actorRole, $member);

            $tenant->users()->detach($member->id);
        });
    }

    private function authorize(Tenant $tenant, User $actor, TenantRole $actorRole, User $member): void
    {
        if ($member->is($actor)) {
            throw TeamActionFailed::yourself();
        }

        // Trava a linha do membro: duas mudanças simultâneas não se atropelam.
        $current = DB::table('tenant_user')
            ->where(['tenant_id' => $tenant->id, 'user_id' => $member->id])
            ->lockForUpdate()
            ->value('role');

        if ($current === null) {
            throw TeamActionFailed::notMember();
        }

        if (! $actorRole->canManage(TenantRole::from($current))) {
            throw TeamActionFailed::cannotManage(TenantRole::from($current));
        }
    }
}
