<?php

namespace App\Modules\Tenancy\Enums;

enum PermissionSlug: string
{
    case MEMBERSHIPS_MANAGE = 'memberships.manage';
    case MEMBERSHIPS_ROLES_MANAGE = 'memberships.roles.manage';
    case ROLES_VIEW = 'roles.view';
    case ROLES_CREATE = 'roles.create';
    case ROLES_UPDATE = 'roles.update';
    case ROLES_DELETE = 'roles.delete';
    case ROLES_PERMISSIONS_MANAGE = 'roles.permissions.manage';
    case SECRETARIAS_VIEW = 'secretarias.view';
    case SECRETARIAS_CREATE = 'secretarias.create';
    case SECRETARIAS_UPDATE = 'secretarias.update';

    case INVITATIONS_VIEW = 'invitations.view';
    case INVITATIONS_MANAGE = 'invitations.manage';

    public static function defaultAdminSlugs(): array
    {
        return [
            self::MEMBERSHIPS_MANAGE->value,
            self::MEMBERSHIPS_ROLES_MANAGE->value,
            self::INVITATIONS_VIEW->value,
            self::INVITATIONS_MANAGE->value,
            self::ROLES_VIEW->value,
            self::ROLES_CREATE->value,
            self::ROLES_UPDATE->value,
            self::ROLES_DELETE->value,
            self::ROLES_PERMISSIONS_MANAGE->value,
            self::SECRETARIAS_VIEW->value,
            self::SECRETARIAS_CREATE->value,
            self::SECRETARIAS_UPDATE->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::MEMBERSHIPS_MANAGE => 'Gerenciar acesso de pessoas',
            self::MEMBERSHIPS_ROLES_MANAGE => 'Definir funções dos membros',
            self::ROLES_VIEW => 'Visualizar funções',
            self::ROLES_CREATE => 'Criar funções',
            self::ROLES_UPDATE => 'Editar funções',
            self::ROLES_DELETE => 'Excluir funções',
            self::ROLES_PERMISSIONS_MANAGE => 'Alterar permissões das funções',
            self::SECRETARIAS_VIEW => 'Visualizar secretarias',
            self::SECRETARIAS_CREATE => 'Criar secretarias',
            self::SECRETARIAS_UPDATE => 'Editar secretarias',
            self::INVITATIONS_VIEW => 'Visualizar convites',
            self::INVITATIONS_MANAGE => 'Gerenciar convites',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MEMBERSHIPS_MANAGE => 'Permite ativar e desativar membros.',
            self::MEMBERSHIPS_ROLES_MANAGE => 'Permite definir as funções que cada pessoa exerce.',
            self::ROLES_VIEW => 'Permite visualizar todas as funções.',
            self::ROLES_CREATE => 'Permite criar novas funções.',
            self::ROLES_UPDATE => 'Permite atualizar os dados das funções.',
            self::ROLES_DELETE => 'Permite excluir funções.',
            self::ROLES_PERMISSIONS_MANAGE => 'Permite gerenciar as permissões atreladas a cada função.',
            self::SECRETARIAS_VIEW => 'Permite visualizar a lista e os detalhes das secretarias.',
            self::SECRETARIAS_CREATE => 'Permite criar novas secretarias.',
            self::SECRETARIAS_UPDATE => 'Permite atualizar os dados das secretarias.',
            self::INVITATIONS_VIEW => 'Permite visualizar a lista e detalhes dos convites.',
            self::INVITATIONS_MANAGE => 'Permite criar, revogar e reenviar convites.',
        };
    }
}
