<?php

namespace App\Helpdesk\Team;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cria uma empresa com o usuário como dono. Expediente e prazos de SLA
 * começam no padrão (SlaClock) até alguém configurar.
 */
class CreateTenant
{
    public function handle(User $owner, string $name, string $slug): Tenant
    {
        return DB::transaction(function () use ($owner, $name, $slug) {
            $tenant = Tenant::create(['name' => $name, 'slug' => $slug]);
            $tenant->users()->attach($owner->id, ['role' => TenantRole::Owner->value]);

            return $tenant;
        });
    }
}
