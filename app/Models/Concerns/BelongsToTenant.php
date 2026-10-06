<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\MissingTenantException;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isola o model por tenant: filtra consultas pelo tenant atual e preenche
 * tenant_id automaticamente na criação.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $tenantId = app(CurrentTenant::class)->id()
                ?? throw MissingTenantException::whileCreating($model::class);

            $model->setAttribute('tenant_id', $tenantId);
        });
    }

    public function initializeBelongsToTenant(): void
    {
        $this->mergeCasts(['tenant_id' => 'integer']);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
