<?php

namespace App\Modules\Tenancy\Enums;

enum PermissionSlug: string
{
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

    public function label(): string
    {
        return match ($this) {
            self::MEMBERSHIPS_ROLES_MANAGE => 'Gerenciar Papéis de Associação',
            self::ROLES_VIEW => 'Visualizar Papéis',
            self::ROLES_CREATE => 'Criar Papéis',
            self::ROLES_UPDATE => 'Editar Papéis',
            self::ROLES_DELETE => 'Excluir Papéis',
            self::ROLES_PERMISSIONS_MANAGE => 'Gerenciar Permissões de Papéis',
            self::SECRETARIAS_VIEW => 'Visualizar Secretarias',
            self::SECRETARIAS_CREATE => 'Criar Secretarias',
            self::SECRETARIAS_UPDATE => 'Editar Secretarias',
            self::INVITATIONS_VIEW => 'Visualizar Convites',
            self::INVITATIONS_MANAGE => 'Gerenciar Convites',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MEMBERSHIPS_ROLES_MANAGE => 'Permite gerenciar a atribuição e revogação de papéis das associações dentro do tenant.',
            self::ROLES_VIEW => 'Permite visualizar todos os papéis do tenant.',
            self::ROLES_CREATE => 'Permite criar novos papéis no tenant.',
            self::ROLES_UPDATE => 'Permite atualizar os dados dos papéis do tenant.',
            self::ROLES_DELETE => 'Permite excluir papéis do tenant.',
            self::ROLES_PERMISSIONS_MANAGE => 'Permite gerenciar as permissões atreladas a cada papel do tenant.',
            self::SECRETARIAS_VIEW => 'Permite visualizar a lista e os detalhes das secretarias do tenant.',
            self::SECRETARIAS_CREATE => 'Permite criar novas secretarias no tenant.',
            self::SECRETARIAS_UPDATE => 'Permite atualizar os dados das secretarias do tenant.',
            self::INVITATIONS_VIEW => 'Permite visualizar a lista e detalhes dos convites do tenant.',
            self::INVITATIONS_MANAGE => 'Permite criar, revogar e reenviar convites do tenant.',
        };
    }
}
