<?php

namespace App\Support\Tenancy;

use LogicException;

class MissingTenantException extends LogicException
{
    public static function whileCreating(string $model): self
    {
        return new self("Não é possível criar [{$model}] sem tenant no contexto ou tenant_id explícito.");
    }
}
