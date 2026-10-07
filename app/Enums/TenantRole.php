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

    /**
     * Se quem tem este papel pode convidar, promover, rebaixar ou remover
     * alguém com o papel informado: só quem está acima na hierarquia.
     * O dono não é gerenciado por ninguém.
     */
    public function canManage(self $role): bool
    {
        return match ($this) {
            self::Owner => $role !== self::Owner,
            self::Admin => $role === self::Agent,
            self::Agent => false,
        };
    }

    /**
     * Papéis que se pode dar a alguém da equipe (o dono nasce com a empresa).
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Agent];
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Dono',
            self::Admin => 'Administrador',
            self::Agent => 'Atendente',
        };
    }
}
