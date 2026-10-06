<?php

namespace App\Support\Tenancy;

use App\Enums\TenantRole;
use App\Models\Tenant;

/**
 * Guarda o tenant ativo do request/job atual.
 *
 * Registrado como binding "scoped", então é descartado ao fim de cada
 * request ou job e nunca vaza contexto entre execuções.
 */
class CurrentTenant
{
    private ?Tenant $tenant = null;

    /**
     * Papel do usuário autenticado no tenant atual (definido no painel).
     */
    private ?TenantRole $role = null;

    public function set(?Tenant $tenant, ?TenantRole $role = null): void
    {
        $this->tenant = $tenant;
        $this->role = $role;
    }

    public function role(): ?TenantRole
    {
        return $this->role;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Executa o callback no contexto do tenant e restaura o contexto anterior.
     *
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        [$previousTenant, $previousRole] = [$this->tenant, $this->role];

        // O papel do usuário vale só para o tenant em que foi definido.
        $this->set($tenant, $tenant->is($previousTenant) ? $previousRole : null);

        try {
            return $callback($tenant);
        } finally {
            $this->set($previousTenant, $previousRole);
        }
    }
}
