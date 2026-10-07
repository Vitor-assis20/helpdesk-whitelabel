<?php

namespace App\Helpdesk\Team;

use App\Enums\TenantRole;
use DomainException;

class TeamActionFailed extends DomainException
{
    public static function cannotManage(TenantRole $role): self
    {
        return new self(match ($role) {
            TenantRole::Owner => 'O dono da empresa não pode ser alterado nem removido.',
            TenantRole::Admin => 'Só o dono da empresa gerencia administradores.',
            TenantRole::Agent => 'Você não tem permissão para gerenciar a equipe.',
        });
    }

    public static function notAssignable(TenantRole $role): self
    {
        return new self("Não é possível dar o papel de {$role->label()} por aqui.");
    }

    public static function yourself(): self
    {
        return new self('Você não pode alterar o próprio acesso. Peça a outro administrador.');
    }

    public static function alreadyMember(): self
    {
        return new self('Essa pessoa já faz parte da equipe.');
    }

    public static function notMember(): self
    {
        return new self('Este usuário não faz parte da equipe desta empresa.');
    }

    public static function invitationUnusable(): self
    {
        return new self('Este convite expirou ou já foi usado. Peça um novo convite.');
    }

    public static function wrongAccount(string $email): self
    {
        return new self("Este convite foi enviado para {$email}. Entre com essa conta para aceitar.");
    }
}
