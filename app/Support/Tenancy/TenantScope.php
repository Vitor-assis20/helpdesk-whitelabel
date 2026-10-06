<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restringe as consultas ao tenant atual.
 *
 * Falha fechado: sem tenant no contexto, a consulta não retorna nada em vez
 * de retornar os dados de todos os tenants. Acesso cruzado intencional deve
 * ser explícito com withoutGlobalScope(TenantScope::class).
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
