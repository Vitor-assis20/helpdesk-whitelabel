<?php

namespace App\Enums;

enum TenantRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Agent = 'agent';

    public function canManageTenant(): bool
    {
        return $this !== self::Agent;
    }
}
